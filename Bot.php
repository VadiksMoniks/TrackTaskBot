<?php 

class Bot {
    private $key = "8135316071:AAGEWaliqcivHVsv1xK3I3uutnP237oIvC8";
    private $apiUrl;
    private $taskStatus = [
        'new' => 'новая',
        'in_progress' => 'в работе',
        'done' => 'сделано'
    ];
    private  $menuButton = [
    'inline_keyboard' => [
        [
            ['text' => 'COMMANDS', 'callback_data' => 'COMMANDS']
        ]
        ]
    ];
    private $commands = [
        'inline_keyboard' => [
        [
            ['text' => 'Create Task', 'callback_data' => "create"],
            ['text' => 'View Tasks', 'callback_data' => "list"]
        ]
        ]
    ];
    private $apiCalls = [
        'createUser' => ['url' => 'http://localhost/task-bot/public/api/user/create', 'method' => 'POST'],
        'create' => ['url' => 'http://localhost/task-bot/public/api/task/store', 'method' => 'POST'],
        'update' => ['url' => 'http://localhost/task-bot/public/api/task/update/{task_id}', 'method' => 'PUT'],
        'delete' => ['url' => 'http://localhost/task-bot/public/api/task/{task_id}', 'method' => "DELETE"],
        'list' => ['url' => 'http://localhost/task-bot/public/api/task/list/{telegram_id}', 'method' => 'GET'],
        'view' => ['url' => 'http://localhost/task-bot/public/api/task/{task_id}', 'method' => 'GET'],
    ];
    private $answers;

    public function __construct() {
        $this->apiUrl = "https://api.telegram.org/bot" . $this->key;
        $this->answers = [
            '/start' => ['text' => "Привет! Выберите действие:", 'reply_markup' => $this->menuButton],
            '/ping' => ['text' => "Pong!"],
            'COMMANDS' => ['text' => "Выберите действие:", 'reply_markup' => $this->commands],
            'create' => ['text' => "Чтобы создать задачу, впишите в строку ввода текст по заданому шаблону: create | <название задачи> | <описание задачи>"],
            'update' => ['text' => 'Чтобы редактировать задачу, впишите в строку ввода текст по заданому шаблону: update | <название задачи> | <описание задачи> | статус(может быть только "в работе" или "сделано") | <id> id для вашей задачи - '],
            'delete' => ['text' => "Удаление задачи"],
            'view' => ['text' => "Просмотр задачи"],
            'list' => ['text' => "Список задач"],
        ];
    }

    public function update() {
        $response = file_get_contents($this->apiUrl . "/getUpdates");
        $data = json_decode($response, true);

        if (!isset($data['result'])) {
            echo "Нет новых сообщений\n";
            return;
        }

        $lastUpdateId = 0;

        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];
            $lastUpdateId = $updateId;

            $info = isset($update['callback_query']) ? $update['callback_query'] : $update['message'];

            $this->APICAll($this->apiCalls['createUser']['url'], $this->apiCalls['createUser']['method'], [
                'telegram_id' => $info['from']['id'],
                'first_name' => $info['from']['first_name'] ? $info['from']['first_name'] : null,
                'last_name' => $info['from']['last_name'] ? $info['from']['last_name'] : null
            ]);

            $data = isset($info['data']) ? $info['data'] : $info['text'];
            $params = explode('_', $data);
            if (array_key_exists($params[0], $this->answers)) {
                $handler = 'handle_' . $params[0];
                if(array_key_exists(1, $params)){
                    $info = array_merge(['parameter' => $params[1]], $info);
                }
                if (method_exists($this, $handler)) {
                    $this->$handler($info);
                } else {
                    $this->sendMessage(array_merge(['chat_id' => $info['from']['id']], $this->answers[$data]));
                }

            }
            else {
                if (str_starts_with($data, 'create |')) {
                    $parts = explode('|', $data);

                    if (count($parts) >= 3) {
                        $title = trim($parts[1]);
                        $description = trim($parts[2]);

                        $this->APICAll($this->apiCalls['create']['url'], $this->apiCalls['create']['method'], [
                            'telegram_id' => $info['from']['id'],
                            'title' => $title,
                            'description' => $description
                        ]);

                        $this->sendMessage(['chat_id' => $info['from']['id'], 'text' => "Задача успешно создана!"]);
                    } else {
                        $this->sendMessage(['chat_id' => $info['from']['id'], 'text' => "Неправильный формат. Введите как:\ncreate | Заголовок | Описание"]);
                    }

                }
                elseif(str_starts_with($data, 'update |')){
                    $parts = explode('|', $data);

                    if (count($parts) >= 5) {
                        $title = trim($parts[1]);
                        $description = trim($parts[2]);
                        $this->callAPI('update', ['task_id' => trim($parts[4])], [
                            'title' => $title,
                            'description' => $description,
                            'status' => array_search(trim($parts[3]), $this->taskStatus),                        
                        ]);
                        $this->sendMessage(['chat_id' => $info['from']['id'], 'text' => "Задача успешно обновлена!"]);
                    } else {
                        $this->sendMessage(['chat_id' => $info['from']['id'], 'text' => "Неправильный формат. Введите как:\update | Заголовок | Описание | id"]);
                    }

                } else {
                    $this->sendMessage(array_merge(['chat_id' => $info['from']['id']], ['text' => 'Вы написали: '.$data]));
                }
            }
            
        }
        
        file_get_contents($this->apiUrl . "/getUpdates?offset=" . ($lastUpdateId + 1));
    }

    private function sendMessage(array $params) {
        $ch = curl_init($this->apiUrl . "/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_exec($ch);
        curl_close($ch);
    }

    private function callAPI(string $action, array $urlParams = [], array $data = [])
    {
        $api = $this->apiCalls[$action];
        $url = $api['url'];
        foreach ($urlParams as $key => $value) {
            $url = str_replace("{" . $key . "}", $value, $url);
        }
        echo $url;
        return $this->APICAll($url, $api['method'], $data);

    }

    private function APICAll(string $url, string $method, array $data = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        if($method == 'POST' || $method == 'PUT'){
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function handle_list($info) {
        $response = $this->callApi('list', ['telegram_id' => $info['from']['id']]);
        $tasks = json_decode($response, true);

        if (is_array($tasks) && !empty($tasks['data'])) {
            $message = "Ваши задачи:\n\n";
            foreach ($tasks['data'] as $task) {
                $message .= "📝 " . $task['title'] . "\n";
            }
        
            $keyboard = ['inline_keyboard' => []];

                foreach ($tasks['data'] as $task) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => $task['title'], 'callback_data' => 'view_' . $task['id']]
                    ];
                }

                $this->sendMessage([
                    'chat_id' => $info['from']['id'],
                    'text' => $message,
                    'reply_markup' => json_encode($keyboard)
                ]);
        }
        else {
            $message = "У вас пока нет задач.";
            $this->sendMessage(['chat_id' => $info['from']['id'], 'text' => $message]);
        }
    }

    private function handle_view($info) {
        $response = $this->callApi('view', ['task_id' => $info['parameter']]);
        $task = json_decode($response, true);
        var_dump($task);
        if (is_array($task) && !empty($task['data'])) {
            $message = "Подробности задачи:\n\n";
            $message .= "Название: ".$task['data']['title']."\n";
            $message .= "Описание: ".$task['data']['description']."\n";
            $message .= "Статус: ".$this->taskStatus[$task['data']['status']]."\n";
            $message .= "Создано: ".$task['data']['created_at']."\n";
            $message .= "Последнее обновление: ".$task['data']['updated_at']."\n";
        } else {
            $message = "Задача не найдена";
        }

        $keyboard = ['inline_keyboard' => [
            [
                ['text' => 'Редактировать', 'callback_data' => 'update_'.$task['data']['id']],
                ['text' => 'Удалить', 'callback_data' => 'delete_'.$task['data']['id']],
            ]
        ]];

        $this->sendMessage([
            'chat_id' => $info['from']['id'],
            'text' => $message,
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    private function handle_update($info) {
        $message = $this->answers['update']['text'].$info['parameter'];
        $this->sendMessage([
            'chat_id' => $info['from']['id'],
            'text' => $message,
        ]);
    }

    private function handle_delete($info) {
        $response = $this->callApi('delete', ['task_id' => $info['parameter']]);
        $task = json_decode($response, true);
        var_dump($task);
        if (is_array($task) && !empty($task['message'])) {
            $message = $task['message']."\n";
        } else {
            $message = "Задача не найдена";
        }

        $this->sendMessage([
            'chat_id' => $info['from']['id'],
            'text' => $message,
        ]);
    }
}
