package controllers;
  
import play.db.jpa.JPA;
import play.Logger;
import play.mvc.Controller;
import com.google.gson.*;
import models.*;
import java.util.Date;
import java.sql.Timestamp;
import java.util.Map;
import java.util.HashMap;
import utils.*;
import com.jamonapi.Monitor;
import com.jamonapi.MonitorFactory;
import java.util.List;
import com.google.gson.Gson;
  
/**
 *  Server-side application sync code sample (with playframework 1.2.X)
 *  1. Receive a JSON with the data to save (POST request)
 *  2. Update the SQL DB on the server with the changes in the received JSON
 *  3. Send (as an answer to the POST) the modified data since the "last_sync" to the client
 */
public class AppSync extends Controller {
  
    /** classes for JSON structure*/
    class Info {
        Long lastSyncDate;
        Timestamp lastSyncTS;
        String userEmail;
        String device_uuid;
        String device_version;
        String device_name;
        String userAgent;
        String appName;
        String lng;
        String mosa_version;
    }
  
    class Data {
        List<CardStat> card_stat;
        List<UserCard> user_card;
        List<Variable> variable;
        List<Stat> stat;
    }
  
    class MetaData {
        Info info;
        Data data;
    }
  
    // CORS preflighted request (for local testing)
    // http://www.w3.org/TR/cors/
    // http://www.nczonline.net/blog/2010/05/25/cross-domain-ajax-with-cross-origin-resource-sharing/
    public static void cors() {
       response.setHeader("Access-Control-Allow-Origin", "*");
       response.setHeader("Access-Control-Allow-Headers", "Origin, Content-Type, Accept");
    }
  
    // main controller method
    public static void sync(String body) {
        Map<String, Object> responseMap = new HashMap<String, Object>();
        try {
            Logger.debug("App sync");
            MetaData md = new Gson().fromJson(body, MetaData.class);
  
            Info info = md.info;
  
            info.lastSyncTS = new Timestamp(0L);
            if (info.lastSyncDate != null) {
                info.lastSyncTS = new Timestamp(info.lastSyncDate);
            }
            Timestamp syncDate = new Timestamp(new Date().getTime());
            Logger.debug("lastSyncTS: " + info.lastSyncTS);
            Logger.debug("syncDate: " + syncDate);
  
  
            // find user or create a new one
            SyncUser user = SyncUser.findUser(info.userEmail, info.device_uuid);
            if (user == null) {
                user = new SyncUser(info.userEmail, info.device_uuid);
            }
            user.save();
  
            // evicting session cache to improve performance
            JPA.em().clear();
  
            // Changes since last sync
            // Do it before start because it may be overwriten
            Map<String, List<Object>> changes = new HashMap<String, List<Object>>();
            changes.put("card_stat", CardStat.find("byAppAndLastSyncDateBetween", userApp, syncDate, info.lastSyncTS).fetch());
            changes.put("user_card", UserCard.find("byAppAndLastSyncDateBetween", userApp, syncDate, info.lastSyncTS).fetch());
            changes.put("variable", Variable.find("byAppAndLastSyncDateBetween", userApp, syncDate, info.lastSyncTS).fetch());
            changes.put("stat", Stat.find("byAppAndLastSyncDateBetween", userApp, syncDate, info.lastSyncTS).fetch());
  
            Data data = md.data;
  
            if (data == null) {
                Logger.warn("data is null");
            } else {
                // Card stats
                List<CardStat> cardStats = data.card_stat;
                if (cardStats != null) {
                    for (CardStat cs : cardStats) {
                        CardStat cs1 = CardStat.find("byCard_idAndApp", cs.card_id, userApp).first();
                        if (cs1 != null) {
                            cs.id = cs1.id;
                            cs = cs.merge();
                        }
                        saveData(cs, userApp, syncDate);
                    }
                }
                JPA.em().flush();
                JPA.em().clear();
  
                // User card
                List<UserCard> userCards = data.user_card;
                if (userCards != null) {
                    for (UserCard uc : userCards) {
                        if (uc.id != null) {
                            UserCard uc1 = UserCard.find("byIdAndApp", uc.id, userApp).first();
                            if (uc1 != null) {
                                uc.pk = uc1.pk;
                                uc = uc.merge();
                            }
                        }
                        saveData(uc, userApp, syncDate);
                    }
                }
                JPA.em().flush();
                JPA.em().clear();
  
                // Variable
                    List<Variable> variables = data.variable;
                    if (variables != null) {
                        for (Variable v : variables) {
                            if (v.name != null) {
                                Variable v1 = Variable.find("byNameAndApp", v.name, userApp).first();
                                if (v1 != null) {
                                    v.id = v1.id;
                                    v = v.merge();
                                }
                            }
                            saveData(v, userApp, syncDate);
                        }
                    }
                    JPA.em().flush();
                    JPA.em().clear();
                
  
                 // Stat
                List<Stat> stats = data.stat;
                if (stats != null) {
                    for (Stat s : stats) {
                        if (s.id != null) {
                            Stat s1 = Stat.find("byIdAndApp", s.id, userApp).first();
                            if (s1 != null) {
                                s.pk = s1.pk;
                                s = s.merge();
                            }
                        }
                        saveData(s, userApp, syncDate);
                    }
                }
            }

  
            responseMap.put("result", "OK");
            responseMap.put("message", "Data updated successfully in the server");
            responseMap.put("data", changes);
            responseMap.put("syncDate", syncDate);
        } catch (Exception e) {
            responseMap.put("result", "ERROR");
            responseMap.put("message", e.getMessage());
            Logger.error(e, e.getMessage());
            Logger.debug(body);
        }
  
        response.setHeader("Access-Control-Allow-Origin", "*");
        renderJSON(responseMap);
    }
  
    /**
     * JSON rendering with SqlDateJsonSerializer
     * @param o object to render
     */
    protected static void renderJSON(Object o) {
        throw new RenderJsonGzip(o, new SqlTimestampJsonSerializer());
    }
}
