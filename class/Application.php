<?php

class Application extends Config {

    private $routingRules = [
        'Application' => [
            'index' => 'Application/actionIndex'
        ],
        'robots.txt' => [
            'index' => 'Application/actionRobots'
        ],
        'debug' => [
            'index' => 'Application/actionDebug'
        ]
    ];

    /**
     * @var $view View
     */
    private $view;

    function __construct() {
        parent::__construct();
        $this->view = new View($this);
        if ($this->requestMethod == 'POST') {
            header('Content-Type: application/json');
            die(json_encode($this->ajaxHandler($_POST)));
        } else {
            //Normal GET request. Nothing to do yet
        }
    }

    public function run() {
        if (array_key_exists($this->routing->controller, $this->routingRules)) {
            if (array_key_exists($this->routing->action, $this->routingRules[$this->routing->controller])) {
                list($controller, $action) = explode(DIRECTORY_SEPARATOR, $this->routingRules[$this->routing->controller][$this->routing->action]);
                call_user_func([$controller, $action]);
            } else { http_response_code(404); die('action not found'); }
        } else { http_response_code(404); die('controller not found'); }
    }

    public function actionIndex() {
        return $this->view->render('index');
    }

    public function actionDebug() {
        return $this->view->render('debug');
    }

    public function actionRobots() {
        return implode(PHP_EOL, ['User-Agent: *', 'Disallow: /']);
    }

    /**
     * Здесь нужно реализовать механизм валидации данных формы
     * @param $data array
     * $data - массив пар ключ-значение, генерируемое JavaScript функцией serializeArray()
     * name - Имя, обязательное поле, не должно содержать цифр и не быть больше 64 символов
     * phone - Телефон, обязательное поле, должно быть в правильном международном формате. Например +38 (067) 123-45-67
     * email - E-mail, необязательное поле, но должно быть либо пустым либо содержать валидный адрес e-mail
     * comment - необязательное поле, но не должно содержать тэгов и быть больше 1024 символов
     *
     * @return array
     * Возвращаем массив с обязательными полями:
     * result => true, если данные валидны, и false если есть хотя бы одна ошибка.
     * error => ассоциативный массив с найдеными ошибками,
     * где ключ - name поля формы, а значение - текст ошибки (напр. ['phone' => 'Некорректный номер']).
     * в случае отсутствия ошибок, возвращать следует пустой массив
     */
    public function actionFormSubmit($data) {
        $errors = [];
        foreach ($data as $item){ //прохід по елементам вхідного масиву
            switch ($item["name"]){ //перебір імен полів
                case "name":{
                    if (!empty($item["value"])){ //якщо пусте ім'я видати помилку
                        if (strlen($item["value"]) <= 64){ //якщо воно не пусте але більше 64 символів видати помилку
                            if (!preg_match("/^[a-zA-Z]+$/", $item["value"])){ //перевірка регулярним виразом на наявність тільки літер в імені, вираз [a-zA-Z]+ означає будь яку кількість вхідних літер
                                $errors += [$item["name"] => "Name has digits!"];
                            }
                        }else{
                            $errors += [$item["name"] => "Name is too long(".strlen($item["value"].")!")];
                        }
                    }else{
                        $errors += [$item["name"] => "Is Empty!"];
                    }
                    break;
                }
                case "phone":{
                    if (!empty($item["value"])) { //якщо пусте значення номеру видати помилку
                        if (!preg_match("/^\+?\d{3}\(?\d{2}\)?\d{3}\-?\d{2}\-?\d{2}$/", $item["value"])){ // перевірка регулярним виразом на відповідність стандарту (+38(097)892-52-40)
                            // в плагіні для створення маски текстового поля вказаний інший стандарт (+380(97)892-52-40) тому робив по плагіну
                            // \+?\d{3}\(?\d{2}\)?\d{3}\-?\d{2}\-?\d{2}$/ вираз розділений на блоки статичних знаків +()- та кількісних циферних входжень \d{3}
                            $errors += [$item["name"] => "Does not meet standards!"];
                        }
                    }else{
                        $errors += [$item["name"] => "Is Empty!"];
                    }
                    break;
                }
                case "email":{
                    if (!preg_match("/^([a-zA-Z0-9_\-\.]+)@([a-zA-Z0-9_\-\.]+)\.([a-zA-Z]{2,5})$/", $item["value"]) and !empty($item["value"])){ //перевірка регулярним виразом паттерну пошти
                        //([a-zA-Z0-9_\-\.]+) будь яке кількісне входження буковб цифер та деяких знаків
                        // ([a-zA-Z0-9_\-\.]+)\.([a-zA-Z]{2,5}) від 2 до 5 блоків розділених крапкою блоків після знаку @
                        //так як регулярний вираз сприймає пустий рядок за помилку виразу перевіримо на пустоту поле і якщо воно не пусте видамо помилку
                        $errors += [$item["name"] => "Invalid email!"];
                    }
                    break;
                }
                case "comment":{
                    if (strlen($item["value"]) <= 1024){ //перевірка довжини коментарію при > 1024 помилка
                        if (preg_match("|<[^>]+>(.*)</[^>]+>|U", $item["value"])){ //перевірка регулярним виразом на наявність першого входження тегів, при знайденому тегу помилка
                            $errors += [$item["name"] => "Has tags!"];
                        }
                    }else{
                        $errors += [$item["name"] => "Comment is too long(".strlen($item["value"].")!")];
                    }
                    break;
                }
            }
        }
        return ['result' => count($errors) === 0, 'error' => $errors];
        //трішки підправив View на наявність лишнього поля для помилок поля коментарів
        //якщо стандарт потрібно було підігнати під завдання не дивлячись на макет phone.min.js можна виправити помилку в цій масці {mask:"+380(##)###-##-##",cc:"UA",cd:"Ukraine",desc_en:"",name_ru:"Украина",desc_ru:""}
    }



    /**
     * Функция обработки AJAX запросов
     * @param $post
     * @return array
     */
    private function ajaxHandler($post) {
        if (count($post)) {
            if (isset($post['method'])) {
                switch($post['method']) {
                    case 'formSubmit': $result = $this->actionFormSubmit($post['data']);
                        break;
                    default: $result = ['error' => 'Unknown method']; break;
                }
            } else { $result = ['error' => 'Unspecified method!']; }
        } else { $result = ['error' => 'Empty request!']; }
        return $result;
    }
}
