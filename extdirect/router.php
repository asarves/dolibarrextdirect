<?php
/*
 * Forked from sencha examples to connect to dolibarr extdirect classes
 */
if (!defined('NOREQUIRESOC'))       define('NOREQUIRESOC', '1');
if (!defined('NOTOKENRENEWAL'))     define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))      define('NOREQUIREMENU', '1');    // If there is no menu to show
if (!defined('NOREQUIREHTML'))      define('NOREQUIREHTML', '1');    // If we don't need to load the html.form.class.php
if (!defined('NOREQUIREAJAX'))      define('NOREQUIREAJAX', '1');
if (!defined("NOLOGIN"))            define("NOLOGIN", '1');      
// If this page is public (can be called outside logged session)

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res) die("Include of main fails");
require('class/extdirect.class.php');
require('config.php');
$debugData = '[]';

/** Action class
 * class to execute extdirect functions
 */
class Action
{
    public $action;
    public $method;
    public $data;
    public $tid;
}

$isForm = false;
$isUpload = false;

if (isset($HTTP_RAW_POST_DATA)) {
    header('Content-Type: text/javascript');
    $data = json_decode($HTTP_RAW_POST_DATA);
} else if (isset($_POST['extAction'])) {
    $isForm = true;
    $isUpload = $_POST['extUpload'] == 'true';
    $data = new BogusAction();
    $data->action = $_POST['extAction'];
    $data->method = $_POST['extMethod'];
    $data->tid = isset($_POST['extTID']) ? $_POST['extTID'] : null; // not set for upload
    $data->data = array($_POST, $_FILES);
} else if (isset($debugData)) {
    $data = json_decode($debugData);
} else { 
    die('Invalid request.');
}

function doRpc($cdata)
{
    global $API;
    
    try {
        if (!isset($API[$cdata->action])) {
            throw new Exception('Call to undefined action: ' . $cdata->action);
        }

        $action = $cdata->action;
        $a = $API[$action];

        doAroundCalls($a['before'], $cdata);

        $method = $cdata->method;
        $mdef = $a['methods'][$method];
        if (!$mdef) {
            throw new Exception("Call to undefined method: $method on action $action");
        }
        doAroundCalls($mdef['before'], $cdata);

        $r = array(
            'type'=>'rpc',
            'tid'=>$cdata->tid,
            'action'=>$action,
            'method'=>$method
        );

        require_once("class/$action.class.php");
        $o = new $action($_SESSION['dol_login']);
        if (isset($mdef['len'])) {
            $params = isset($cdata->data) && is_array($cdata->data) ? $cdata->data : array();
        } else {
            $params = array($cdata->data);
        }
        error_reporting(0); // comment for debugging or change 0 to E_ALL
        if (!object_analyse_sql_and_script($params, 0)) {
            $result = VULNERABILITYERROR;
        } else {
            if (ExtDirect::checkDolVersion() < 0) {
                $result = COMPATIBILITYERROR;
            } else {
                $result = call_user_func_array(array($o, $method), $params);
            }
        }
        if (is_int($result) && ($result < 0)) {
            $error = new stdClass;
            if ($result > CONNECTERROR) {
                $error->message = "Error $result from dolibarr: $method on action $action";
            } else {
                switch ($result) {
                    case CONNECTERROR:
                        $error->message = "Connect Error: $method on action $action";
                        break;
                    case PERMISSIONERROR:
                        $error->message = "Permission Error: $method on action $action";
                        break;
                    case SQLERROR:
                        $error->message = "SQL Error: $method on action $action";
                        break;
                    case UPTADEERROR:
                        $error->message = "Update Error: $method on action $action";
                        break;
                    case PARAMETERERROR:
                        $error->message = "Parameter Error: $method on action $action";
                        break;
                    case VULNERABILITYERROR:
                        $error->message = "Vulnerability Error: $method on action $action";
                        break;
                    case COMPATIBILITYERROR:
                        $error->message = "Compatibility Error: $method on action $action";
                        break;
                    default:
                        "Error $result from server: $method on action $action";
                        break;
                }
            }
            
            $r['result'] = $result;
            throw new Exception($error->message);
        } else {
            $r['result'] = $result;
        }

        doAroundCalls($mdef['after'], $cdata, $r);
        doAroundCalls($a['after'], $cdata, $r);
    }
    catch(Exception $e){
        $r['type'] = 'exception';
        $r['message'] = $e->getMessage();
        $r['where'] = $e->getTraceAsString();
    }
    return $r;
}
/**
 * loop through methods
 * 
 * @param pointer &$fns method(s)
 * @param pointer &$cdata parameters
 * @param pointer &$returnData return parameter
 * @return nothing 
 */
function doAroundCalls(&$fns, &$cdata, &$returnData=null)
{
    if (!$fns) {
        return;
    }
    if (is_array($fns)) {
        foreach ($fns as $f) {
            $f($cdata, $returnData);
        }
    } else {
        $fns($cdata, $returnData);
    }
}

/**
 * Security: Return true if OK, false otherwise.
 *
 * @param       unknown_type        &$var       Object/Array to check
 * @param       int     $type       1=GET, 0=POST, 2=PHP_SELF
 * @return      boolean                 false if ther is an injection
 */
function object_analyse_sql_and_script(&$var, $type)
{
    if (is_array($var) || is_object($var)) {
        foreach ($var as $key => $value) {
            if (object_analyse_sql_and_script($value, $type)) {
                if (is_array($var)) {
                    $var[$key] = $value;
                } else {
                    $var->$key = $value;
                }
            } else {
                return false;
            }
        }
        return true;
    } else {
        //print_r($var);
        return (test_sql_and_script_inject($var, $type) <= 0);
    }
}

$response = null;
if (is_array($data)) {
    $response = array();
    foreach ($data as $d) {
        $response[] = doRpc($d);
    }
} else {
    $response = doRpc($data);
}
if ($isForm && $isUpload) {
    echo '<html><body><textarea>';
    echo json_encode($response);
    echo '</textarea></body></html>';
} else {
    echo json_encode($response);
}