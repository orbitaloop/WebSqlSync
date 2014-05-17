<?

/*
* Sync Controller
*
* Description: Controller that Sync data with a client App. 
* It is designed to work with WebSqlSync (https://github.com/orbitaloop/WebSqlSync)
*
* Input: send a Json trought a Post Request whit this structure:
*
*   clientData: {
*       "info":{
*           "source":"Client App Name",
*           "lastSyncDate": timestamp (javascript -> millisecond)
*       },
*       "data":{
*           "modelName":[{model1},{model2}],
*           "modelName":[{model1},{model2}]
*       }    
*   }
* 
* Output: respond with a Json structure like this:
*
*   serverAnswer: {
*       result: 'OK', //or ERROR
*       message: 'usefull message',
*       syncDate: timestamp (javascript -> millisecond),
*       data: {
*           "modelName":[{model1},{model2}],
*           "modelName":[{model1},{model2}]
*       }    
*   }
*
* Note: 
* - Any model involved in the sync need to have a created and updated field stored in Unix TimeStamp
* - The PK have to be called: server_id => client_id
* - If there are relation between models the fields have to be called: server_fk_modelname => client_fk_modelname where modelname is the foreign model name in string to lower
*/


class SyncController extends Controller
{

	public function filters()
    {
        /*return array('FormatData');*/
    }

    //tentativo di scrivere un filtro da applicare prima di tutte le azioni ma non ha funzionato
	public function filterFormatData($filterChain){
		$data = json_decode(file_get_contents("php://input"));
		return true;
	}
	private $serverAnswer = array("result"=> '',"message" => '', "syncDate" => '',"data" => array());

    //string transformation utils
    private function _snakeToCamel($val) {  
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $val)));  
    }  

    private function _camelToSnake($val) {  
        return preg_replace_callback('/[A-Z]/',  create_function('$match', 'return "_" . strtolower($match[0]);'),  $val);  
    } 
    //end string

    //this is the default action triggered by the post request
	public function actionAdmin() {

        //grab the data from Json Post
		$data = json_decode(file_get_contents("php://input"));

        $this->_checkAuth($data->info->user, $data->info->pass);

        //set the default message
		$this -> serverAnswer['result'] = 'OK';
		$this -> serverAnswer['message'] = "Has Workerd Out!";

        //get last sync date for query server data
		$lastSync = $data->info->lastSyncDate/1000;

        //define an empty array to store couple of values (client_id => server_id)
        $ids_arr = array();

        //cicle trough POST data and update the server DB
        foreach ($data->data as $key => $value) {

            //get the class name from the client Data
            $className = $this->_snakeToCamel($key);

            //if a model exists
            if(isset($data->data->$key)){

                //for each model item
                foreach ($data->data->$key as $obj) {

                    //search for an existing model to update or create an empty one
                    $model = $className::model()->findByPk($obj->server_id);
                    if(!$model)
                        $model = new $className();

                    //assign the attribute to the model
                    $model->attributes = (array)$obj;

                    //look for existing relation
                    $metaData = $model->getMetaData();

                    //if a relation exists
                    if(isset($metaData->relations)){
                        //cicle trought relation
                        foreach ($metaData->relations as $relation) {

                            //if is defined a Belongs To Relation
                            if(get_class($relation) == "CBelongsToRelation"){

                                //set the default name for relation
                                $server_fk = "server_fk_".strtolower($relation->className);
                                $client_fk = "client_fk_".strtolower($relation->className);

                                //if is not defined a server foreign key check in the array to find the right key
                                if(!$model->$server_fk){
                                    $model->$server_fk = array_search($obj->$client_fk, $ids_arr[strtolower($relation->className)]);

                                }

                            }
                        }
                        
                    }

                    //if model is saved trough an error
                    if (!$model->save(false)){
                        $this -> serverAnswer['result'] = 'ERROR';
                        $this -> serverAnswer['message'] = json_encode($model->getErrors());
                    }
                    else
                        //save the couple of client_id and server_id
                        $ids_arr[$key][(int)$model->server_id] = $obj->client_id;
                        
                }
            }
        }

        if ($lastSync != 0)
            $lastSync = time();

        //building a response obj
		$results = new stdClass();
        
        //clicle trough post class and send update to the client
        foreach ($data->data as $key => $value) {
            $i = 0;

            //transform post object in class name
            $className = $this->_snakeToCamel($key);

            //select all the model created or updated after lastSync
            $list = $className::model()->findAll('status = 1 AND updated >= '.$lastSync);
            foreach ($list as $value) {
                if(empty($results->{$key}))
                      $results->{$key} = [];
                $results->{$key}[$i] = $value->attributes;
                if(isset($ids_arr[$key][$value->server_id]))
                    $results->{$key}[$i]['client_id'] = $ids_arr[$key][$value->server_id];
                $i++;
            }
        }

        //create the syncDate to send back
		$this -> serverAnswer['syncDate'] = strtotime("now")*1000;
		
        //assign the data to the answer
		$this -> serverAnswer['data'] = $results;

        //send the answer
		$this->_sendResponse(200, CJSON::encode($this -> serverAnswer));
	}

	//allow cors
    public function actionPreflight() {
        $content_type = 'application/json';
        $status = 200;
 		
		header("Access-Control-Allow-Origin: *");

        // set the status
        $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
        header($status_header);
 
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization");
        header('Content-type: ' . $content_type);
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

   
    }

    //get the response Status Code
    private function _getStatusCodeMessage($status)
    {
        // these could be stored in a .ini file and loaded
        // via parse_ini_file()... however, this will suffice
        // for an example
        $codes = Array(
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
        );
        return (isset($codes[$status])) ? $codes[$status] : '';
    }

    //format and send the response
    private function _sendResponse($status = 200, $body = '', $content_type = 'text/html')
    {
        // set the status
        $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
        header($status_header);
        // and the content type
        header('Content-type: ' . $content_type);
     
        // Allows from any origin
        // Allows a header called Authorization
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization");

        // pages with body are easy
        if($body != '')
        {
            // send the body
            echo $body;
        }
        // we need to create the body if none is passed
        else
        {
            // create some body messages
            $message = '';
     
            // this is purely optional, but makes the pages a little nicer to read
            // for your users.  Since you won't likely send a lot of different status codes,
            // this also shouldn't be too ponderous to maintain
            switch($status)
            {
                case 401:
                    $message = 'You must be authorized to view this page.';
                    break;
                case 404:
                    $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
                    break;
                case 500:
                    $message = 'The server encountered an error processing your request.';
                    break;
                case 501:
                    $message = 'The requested method is not implemented.';
                    break;
            }
     
            // servers don't always have a signature turned on 
            // (this is an apache directive "ServerSignature On")
            $signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];
     
            // this should be templated in a real-world solution
            $body = '
                <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
                <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
                    <title>' . $status . ' ' . $this->_getStatusCodeMessage($status) . '</title>
                </head>
                <body>
                    <h1>' . $this->_getStatusCodeMessage($status) . '</h1>
                    <p>' . $message . '</p>
                    <hr />
                    <address>' . $signature . '</address>
                </body>
                </html>';
     
            echo $body;
        }
        Yii::app()->end();
    }

    //this will check autentication on a bamboo sistem, needs to be included in the needed action
    private function _checkAuth($user, $pwd)
    {
        // Check if we have the USERNAME and PASSWORD HTTP headers set?
        if(!(isset($user) and isset($pwd))) {
            // Error: Unauthorized
            $this->_sendResponse(401);
        }
        $username = $user;
        $password = $pwd;

        // Find the user
        $user=User::model()->find('LOWER(username)=?',array(strtolower($username)));
        if($user===null) {
            // Error: Unauthorized
            $this->_sendResponse(401, 'Error: User Name is invalid');
        } else if(!$user->validatePassword($password)) {
            // Error: Unauthorized
            $this->_sendResponse(401, 'Error: User Password is invalid');
        }
    }

}
?>