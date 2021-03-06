#!/usr/bin/env php
<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

require_once(__dir__ . "/vendor/autoload.php");

use Workerman\Worker;
use PHPSocketIO\SocketIO;

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

use Sowe\PHPPeerServer\Controller;

$logger = new Logger("");
$formatter = new LineFormatter("[%datetime%]:%level_name%: %message%\n", "Y-m-d\TH:i:s");
$stream = new StreamHandler(LOG_PATH, Logger::DEBUG);
$stream->setFormatter($formatter);
$logger->pushHandler($stream);
$handler = new ErrorHandler($logger);
$handler->registerErrorHandler([], false);
$handler->registerExceptionHandler();
$handler->registerFatalHandler();

$io = new SocketIO(PORT, array(
    'ssl' => array(
        'local_cert'  => CERT_CA,
        'local_pk'    => CERT_KEY,
        'verify_peer' => false,
        'allow_self_signed' => true,
        'verify_peer_name' => false
    )
));


$controller = new Controller($io, $logger);

$io->on('connection', function ($socket) use ($controller) {

    $controller->connect($socket);

    $socket->on("error", function ($exception) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->handleException($client, $exception);
        }
    });

    $socket->on("disconnect", function ($reason) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            if($reason == $client->getId()){
                $reason = "leaving";
            }
            $controller->disconnect($client, $reason);
        }
    });

    $socket->on("message", function($message) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->message($client, $message);
        }
    });
    
    $socket->on("toggle", function($resource) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->toggleResource($client, $resource);
        }
    });

    $socket->on("create", function($name, $password) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->createRoom($client, $name, $password);
        }
    });
    
    $socket->on("join", function($roomId, $password) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->joinRoom($client, $roomId, $password);
        }
    });

    $socket->on("leave", function() use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->leaveRoom($client);
        }
    });

    $socket->on("kick", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->kickFromRoom($client, $userId);
        }
    });

    $socket->on("ban", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->banFromRoom($client, $userId);
        }
    });

    $socket->on("unban", function($userId) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->unbanFromRoom($client, $userId);
        }
    });

    /**
     * Calls
     */
    $socket->on("candidate", function($callId, $candidate) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->candidate($client, $callId, $candidate);
        }
    });

    $socket->on("offer", function($callId, $offer) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->offer($client, $callId, $offer);
        }
    });
    $socket->on("answer", function($callId, $answer) use ($socket, $controller) {
        $client = $controller->getClient($socket);
        if($client !== false){
            $controller->answer($client, $callId, $answer);
        }
    });

});

Worker::runAll();