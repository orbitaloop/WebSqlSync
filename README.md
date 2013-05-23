WebSqlSync 
=====================
Automatically synchronize a local WebSql database (SQLite of the browser) with a server:
------------------

 - 2 way sync : client <-> server
 - Incremental synchronization (send only the necessary data)
 - Works offline. All data changes are tracked and synchronized with the server once the connection returns
 - Support for replicating changes to multiple devices
 - works with any JS web app or phonegap app (iOS, Android, etc.), without changing your code
 - MIT licence

Very easy to integrate and to use (2 functions to call : initSync and syncNow), non intrusive with your existing code.

Installing
==========

 - just copy the src/webSqlSync.js file in your project and include it in your html :
 
        script src="lib/webSqlSync.js" type="application/x-javascript" charset="utf-8">/script>"


Usage
=============

## Initialize
You need to initialize the lib (at each startup for example).

It will automatically create 2 tables (if they don't already exists). The table new_elem is used to store all the new or modified elements and the table sync_info is used to store the date of the last sync. It will also create SQLite triggers in order to watch the INSERT or UPDATE on the tables you want to synchronize (to automatically insert the modified elements in the new_elem table):

    DBSYNC.initSync(TABLES_TO_SYNC, webSqlDb, sync_info, 'http://www.myserver.com', callBackEndInit);

Where TABLES_TO_SYNC is the list of DB table that you want to sync with the server, ex :

    TABLES_TO_SYNC = [
        {tableName : 'table1', idName : 'the_id'},
        {tableName : 'table2'} //if idName not specified, it will assume that it's "id"
    ];

And sync_info can be everything you want. It's useful to identify the client, because it will be sent to the server (it can be a user email or login, a device UUID, etc..)

## Synchronize
To start the synchronization, you need to call the syncNow function. You can call it every X seconds, or after some changes for example :

    DBSYNC.syncNow(callBackSyncProgress, function(result) {
         if (result.syncOK === true) {
             //Synchronized successfully
         }
    });
	
Where callBackSyncProgress is a function called at every step of the synchronization (useful to show a progress bar with status if there is a lot of data to synchronize) :

    callBackSyncProgress: function(message, percent, msgKey) {
         $('#uiProgress').html(message+' ('+percent+'%)');
    },

Client / server communication
=============
Currently, there is no generic server code (contribution are welcome), but you can find  example server code (in php & java) in the directory server_src_/.

You can also run the QUnit test to understand the communication between the client (web app) and the server. 

Here is a scenario to show an example of input / output data between the client and the server:

## client output :
It's always the client who initiate the synchronization. So, let's say, in the client app (ex. mobile app), WebSqlSync has detected that 2 rows of the table "card_stat" has been modified (or created). So when the syncNow method is called, it will send to the server the following JSON:

	clientData: {
	    "info": {/* the info to identify the user. It's the obect "sync_info" in parameter of the initSync method. You can put everything you need to identify the client */
	        "userEmail": "testSafari2@gmail.com",
	        "userAgent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/534.52.7 (KHTML, like Gecko) Version/5.1.2 Safari/534.52.7",
	        "lastSyncDate": 1326553035406,/* added automatically by WebSqlSync*/
	    },
	    "data": {/* WebSQL detect the 2 modified objects and send them. If there is no change on the client, data will be empty */
	        "card_stat": [{
	            "card_id": 100330,
	            "firstViewTime": 1326845243.743,
	            "previousInterval": 0,
	            "interval": 4.091382259037346,
	        },
	        {
	            "card_id": 100340,
	            "firstViewTime": 1326845248.655,
	            "previousInterval": 0,
	            "interval": 4.197426769416779,
	        }
	    }
	 }

## server output :
The server receives the previous client data, and should save it in a DB (INSERT OR REPLACE). 
The DB schema on the server side should be nearly identical as the client DB, except that you should add the following columns (at least):
 - client_id (ex. email address like in this example, in order to identify the client, because the table on the server side will have all the data of the different clients)
 - last_sync_date with the current date (now). All the sync dates are managed in the server side in order to avoid problems with time zone or clients with wrong date.

Once the server has saved the client data, it should look if there is more recent data to send to the client. 
To do that, it will use the clientData.lastSyncDate he just received and compare to the server column 'last_sync_date' 

ex. of SQL request : select * from card_stat where card_stat.last_sync_date > clientData.lastSyncDate

Then, it should send the current JSON to the client:

    serverAnswer: {/* Ex. of server answer : */
        result: 'OK',//or 'ERROR'
        message: 'Data updated sucessfuly in the server ' + self.serverUrl,//or a useful error message
        syncDate: '1327075596522',//The server return the current date that will be used for the next sync (the server handle the sync date to avoid pb with wrong date on the client)
        data: {/*data that has been changed since the last sync. If there is no changes in the server, data will be empty */
            card_stat: [{
                card_id: '123456789',//New Data on the server that is not on the client
                due: 1326592755.5729024,
                eFactor: 2.5,
                firstViewTime: 1326553031.718,
                interval: 0.4597668391498737
            }]
        }
    }


I hope it will help you to implement your own server logic, or provide an example of sever code in another language (contributions are welcome!).


## Limitations:

 - DELETE are not handled. But an easy workaround is to do a logic delete with an update (ex. UPDATE elm SET flag='DELETED')
 - There are no example of generic server side sync for now. But there are some examples of server code in different languages (but you will need to adapt it to your needs). Check the server_src_/ directory. Contribution of server code are welcome (generic or not)!!
 - Need to add even more JQunit test cases. But this code is working on more than 18 apps (iOS/Android) in production, with millions of db rows synchronized
 - ~~There is one dependency to JQuery (used only to send data with AJAX, look for jQuery.ajax). I welcome any pull request to remove this dependency~~ DONE, thank you Takeno
