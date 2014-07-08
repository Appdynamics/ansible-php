<?php
function _fail_json(array $data = array())
{
    $data['failed'] = true;
    print json_encode($data);
    exit(1);
}

function _ansible_get_backtrace()
{
    if (version_compare(PHP_VERSION, '5.3.6', '<=')) {
        return debug_backtrace(false);
    }
    else {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
    }

    return debug_backtrace();
}

function _ansible_exception_handler(\Exception $exception)
{
    _fail_json(array(
        'msg' => $exception->getMessage(),
        'line' => $exception->getLine(),
        'bt' => $exception->getTrace(),
    ));
}

function _ansible_error_handler($errno, $msg, $file, $line)
{
    _fail_json(array(
        'errno' => $errno,
        'msg' => $msg,
        'file' => $file,
        'line' => $line,
        'bt' => _ansible_get_backtrace(),
    ));
}

function _ansible_on_shutdown()
{
    $err = error_get_last();

    if ($err === null) {
        return;
    }

    _ansible_error_handler($err['type'], $err['message'], $err['file'], $err['line']);
}

set_error_handler('_ansible_error_handler');
register_shutdown_function('_ansible_on_shutdown');
set_exception_handler('_ansible_exception_handler');
