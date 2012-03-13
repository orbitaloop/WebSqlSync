WebSqlSync
=====================
Automatically synchronize a local WebSql database (SQLite in the navigator) to a server :

 - 2 way sync : client <-> server
 - Incremental synchronization (send only the necessary data)
 - works with webapp and phonegap app (WebSql or PGSQLitePlugin)

Very easy to integrate and to use (2 functions to call : initSync and syncNow), non intrusive with your existing code.

Installing
==========

 - just copy the webSqlSync.js file in your project and include it in your html :
 
        script src="lib/webSqlSync.js" type="application/x-javascript" charset="utf-8">/script>"

Usage
=============

## Initialize
You need to initialize the lib (at each startup for example).

It will automatically create 2 tables (if they don't already exists). The table new_elem is used to store all the new or modified elements and the table sync_info is used to store the date of the last sync. It will also create SQLite triggers in order to watch the INSERT or UPDATE on the tables you want to synchronize (to automatically insert the modified elements in the new_elem table):

    DBSYNC.initSync(TABLES_TO_SYNC, webSqlDb, sync_info, 'http://www.myserver.com', callBackEndInit);

Where TABLES_TO_SYNC is the list of table that you want to sync with the server, ex :

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


### Limitations:

 - DELETE are not handled. But an easy workaround is to do a logic delete with an update (ex. UPDATE elm SET flag='DELETED')
 - There are no example of server side sync for now. But I plan to commit our server code as an example in Java with #playframework (but it's not a generic code)
 - The JQunit tests are too basic. Need to add more test cases. But this code is working on my 13 apps (iOS/Android) in production, with millions of data synchronized
 - There is one dependency to JQuery (used only to send data with AJAX, look for jQuery.ajax). I welcome any pull request to remove this dependency (should be tested in Chrome, Safari, iOS WebKit and Android WebKit)
