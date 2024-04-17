<?php

namespace App\WebSocket;

use Illuminate\Support\Facades\Redis;
use localzet\Server;
use localzet\Server\Connection\AsyncTcpConnection;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class SocketController implements MessageComponentInterface
{
    protected $clients;
    protected $userSessions;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $this->clients->attach($connection);
    }

    public function onClose(ConnectionInterface $connection)
    {
        $this->clients->detach($connection);

        if ($connection->user_id) {
            unset($this->userSessions[$connection->user_id]);
            Redis::srem('active_users', $connection->user_id);
        }
    }

    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        $connection->close();
    }

    public function onMessage(ConnectionInterface $connection, $msg)
    {
        $data = json_decode($msg);
        $user_id = $data->user_id;

        switch ($data->event) {
            case 'connect':
                if ($user_id) {
                    $connection->user_id = $user_id;
                    $this->userSessions[$connection->user_id] = $connection;
                    Redis::sadd('active_users', $user_id);
                }
                break;
            default:
                if ($user_id && Redis::sismember('active_users', $user_id)) {
                    $message = json_encode(['event' => $data->event, 'data' => $data->data]);
                    $this->userSessions[$user_id]->send($message);
                }
        }
    }

    public static function emit($user_id, $event, $data)
    {
        $server = new Server();
        $server->onServerStart = function () use ($user_id, $event, $data) {
            $ws = new AsyncTcpConnection("ws://127.0.0.1:" . env('WS_PORT', 8080));

            $ws->onWebSocketConnect = function (AsyncTcpConnection $connection) use ($user_id, $event, $data) {
                $message = json_encode(['user_id' => $user_id, 'event' => $event, 'data' => $data]);
                $connection->send($message);
                $connection->close();
                Server::stopAll();
            };

            $ws->connect();
        };
        $server->run();
    }
}
