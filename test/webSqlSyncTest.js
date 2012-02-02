/**
 * Unit Test with QUnit (from JQuery)
 */
/*
 Copyright (c) 2012, Samuel Michelot
 Permission is hereby granted, free of charge, to any person obtaining a
 copy of this software and associated documentation files (the "Software"),
 to deal in the Software without restriction, including without limitation the
 rights to use, copy, modify, merge, publish, distribute, sublicense,
 and/or sell copies of the Software, and to permit persons to whom the Software
 is furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.
 
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
$(document).ready(function(){
    var self = this;
    
    _syncProgress = function(percent, msgKey, message){
		console.log(message+' ('+percent+'%)');
    };
    
    /***************************************************************************
     * Test
     ***************************************************************************/
    module("With server mockup");
    //-----------------------------------------------------------------------------------------
    //server mockup :
    old_sendDataToServer = DBSYNC._sendDataToServer;
    DBSYNC._sendDataToServer = function(dataToSync, callBack){
        callBack(SYNCDATA.serverData);
    };
	//
	window.onerror = function(message, url, line){
		console.error('Error: msg='+message+' url='+url+' line='+line);
    };
    
    test("without local data ", function(){
    
        stop();//Handle asynchronous testing
        SYNCDATA.initTestDb(function(){
            DBSYNC.initSync(SYNCDATA.tableToSync, SYNCDATA.database, SYNCDATA.sync_info, SYNCDATA.url, function(firstSync){
                ok(firstSync, 'this is a first sync');
                DBSYNC.syncNow(_syncProgress, function(){
                
                    _test1CheckContent();
                    
                });
            });
        });
    });
    _test1CheckContent = function(){
        SYNCDATA.database.transaction(function(transaction){
            transaction.executeSql('SELECT count(*) FROM card_stat', [], function(tx, result){
                var nb_card_stat = result.rows.item(0)['count(*)'];
                ok(nb_card_stat === SYNCDATA.serverData.data.card_stat.length, 'nb_card_stat');
            });
            transaction.executeSql('SELECT count(*) FROM stat', [], function(tx, result){
                var nb_stat = result.rows.item(0)['count(*)'];
                ok(nb_stat === SYNCDATA.serverData.data.stat.length, 'nb_stat');
            });
            transaction.executeSql('SELECT count(*) FROM user_card', [], function(tx, result){
                var nb_user_card = result.rows.item(0)['count(*)'];
                ok(nb_user_card === SYNCDATA.serverData.data.user_card.length, 'nb_user_card');
            });
            transaction.executeSql('SELECT count(*) FROM variable', [], function(tx, result){
                var nb_variable = result.rows.item(0)['count(*)'];
                ok(nb_variable === SYNCDATA.serverData.data.variable.length, 'nb_variable');
                
                start();//the last call run the asynchronous testing
            });
        });//tx
    };
    //to test the first sync of a prepopulated DB
    test("with previous local data ", function(){
        stop();//Handle asynchronous testing
        SYNCDATA.initTestDb(function(){
			SYNCDATA.insertTestData1(function(){
            	DBSYNC.initSync(SYNCDATA.tableToSync, SYNCDATA.database, SYNCDATA.sync_info, SYNCDATA.url, function(firstSync){
                	ok(firstSync, 'this is a first sync');
                    DBSYNC.syncNow(_syncProgress, function(){
                        _test2CheckContent(function() {
							start();//the last call run the asynchronous testing
						});
                    });
                });
            });
        });
    });
	_test2CheckContent = function(callback){
        SYNCDATA.database.transaction(function(transaction){
            transaction.executeSql('SELECT count(*) FROM card_stat', [], function(tx, result){
                var nb_card_stat = result.rows.item(0)['count(*)'];
                ok(nb_card_stat === 3 + SYNCDATA.serverData.data.card_stat.length - 2, 'nb_card_stat');//-2 because the server update 2 existing data
            });
            transaction.executeSql('SELECT count(*) FROM stat', [], function(tx, result){
                var nb_stat = result.rows.item(0)['count(*)'];
                ok(nb_stat === 2 + SYNCDATA.serverData.data.stat.length - 1, 'nb_stat');
            });
            transaction.executeSql('SELECT count(*) FROM user_card', [], function(tx, result){
                var nb_user_card = result.rows.item(0)['count(*)'];
                ok(nb_user_card === 1 + SYNCDATA.serverData.data.user_card.length - 1, 'nb_user_card');
            });
            transaction.executeSql('SELECT count(*) FROM variable', [], function(tx, result){
                var nb_variable = result.rows.item(0)['count(*)'];
                ok(nb_variable === 1 + SYNCDATA.serverData.data.variable.length - 1, 'nb_variable');
				callback();
            });
        });//tx
    };
	//to test the discovery of inserted elements (table new_elem, managed with triggers)
	test("with local data inserted after", function(){//
        stop();//Handle asynchronous testing
        SYNCDATA.initTestDb(function(){
            DBSYNC.initSync(SYNCDATA.tableToSync, SYNCDATA.database, SYNCDATA.sync_info, SYNCDATA.url, function(firstSync){
                ok(firstSync, 'this is a first sync');
                SYNCDATA.insertTestData1(function(){
                    DBSYNC.syncNow(_syncProgress, function(){//TODO SAM if faut intercepter le sendDataToServer pour vérifier ce qui est envoyé au server...
                        _test2CheckContent(function() {
							start();//the last call run the asynchronous testing
						});
                    });
                });
                
            });
        });
    });

	
    module("Sync with the real server");
    //-----------------------------------------------------------------------------------------
	 test("only test send data", function(){
        DBSYNC._sendDataToServer = old_sendDataToServer;
        stop();//Handle asynchronous testing
        DBSYNC._sendDataToServer(SYNCDATA.realDataSent, function(serverAnswer) {
			 ok(serverAnswer.result === "OK", serverAnswer.message);
			 start();//the last call run the asynchronous testing
		});
    });
	test("only test send client data (server error?)", function(){
        DBSYNC._sendDataToServer = old_sendDataToServer;
        stop();//Handle asynchronous testing
        DBSYNC._sendDataToServer(SYNCDATA.dataSent3, function(serverAnswer) {
			 ok(serverAnswer.result === "OK", serverAnswer.message);
			 start();//the last call run the asynchronous testing
		});
    });
	 test("only test send lot of data (server server error because card not found?)", function(){
        DBSYNC._sendDataToServer = old_sendDataToServer;
        stop();//Handle asynchronous testing
        DBSYNC._sendDataToServer(SYNCDATA.bigData4, function(serverAnswer) {
			 ok(serverAnswer.result === "OK", serverAnswer.message);
			 start();//the last call run the asynchronous testing
		});
    });
	 test("only test send lot of data (server perf test)", function(){
        DBSYNC._sendDataToServer = old_sendDataToServer;
        stop();//Handle asynchronous testing
        //TODO : if it take too long on the iPhone app to send the JSON, it could be interesting to compress it with HPack (gzip would be too slow on client) :
		//http://web-resource-optimization.blogspot.com/2011/06/json-compression-algorithms.html
        DBSYNC._sendDataToServer(SYNCDATA.bigDataSent, function(serverAnswer) {
			 ok(serverAnswer.result === "OK", serverAnswer.message);
			 start();//the last call run the asynchronous testing
		});
    });
	
    test("with local data and the real server", function(){
        DBSYNC._sendDataToServer = old_sendDataToServer;
        stop();//Handle asynchronous testing
        SYNCDATA.initTestDb(function(){
            DBSYNC.initSync(SYNCDATA.tableToSync, SYNCDATA.database, SYNCDATA.sync_info, SYNCDATA.url, function(firstSync){
                SYNCDATA.insertTestData1(function(){
                    DBSYNC.syncNow(_syncProgress, function(syncResult){
                        //TODO checking with the ok() function
                        ok(syncResult.syncOK, syncResult.message);
                        start();//the last call run the asynchronous testing
                    });
                });
                
            });
        });
    });
    //module("Sync Errors cases");
    //-----------------------------------------------------------------------------------------
    //TODO

});
