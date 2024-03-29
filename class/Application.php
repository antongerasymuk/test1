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

        $errors = [];  //Отсутствие ошибок

        foreach ($data as $row) {
            $regex = false;
            $error = false;

            $secondExp = false;

            switch ($row['name']) {
                case 'name':
                    $regex = '/^([A-Za-zА-Яа-яЁё]+)$/';
                    $error = 'неправильный формат имени';
                    //$regex ='/^\D{1,64}$/';
                    //$secondExp = strpbrk($row['value'], '1234567890');

                    //$error = 'имя содержит цифры';

                    if (strlen($row['value']) == 0) {
                        $secondExp =  true;
                        $error = 'имя не может быть пустым';
                    }

                    if (strlen($row['value']) > 64) {
                        $secondExp =  true;
                        $error = 'имя слишком длинное';
                    }

                    break;
                case 'phone':
                    //$regex = '/^[+380]*[(]{1}[1-9]{2}[)]{1}[-0-9]*$/';

                    if (strlen(str_replace(['_','-','+', '(', ')'], '',$row['value'])) < 11) {
                        $secondExp =  true;
                        $error = 'неправильный тел';
                    }

                    if (strlen($row['value']) == 0) {
                        $secondExp =  true;
                        $error = 'тел не может быть пустым';
                    }

                    break;
                case 'email':
                    if (empty($row['value'])) continue;
                    $secondExp = filter_var($row['value'], FILTER_VALIDATE_EMAIL) == false;

                    //$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
                    $error = 'неправильный имейл';

                    break;
                case 'comment':
                    if (empty($row['value'])) continue;
                    $secondExp =
                    (strip_tags($row['value']) !== $row['value'])
                    || (htmlspecialchars($row['value']) !== $row['value']);
                    //|| (mysql_real_escape_string($row['value']) !== $row['value']);
                    $error = 'неправильный коммент';

                    if (strlen($row['value']) > 1024) {
                        $secondExp =  true;
                        $error = 'коммент слишком длинный';
                    }

                    //$regex = '/^[\s\S]{0,1024}$/';
                    break;
            }

            $regex = empty($regex) ? false : !preg_match($regex, $row['value']);


            if ($regex || $secondExp) {
                $errors[$row['name']] = $secondExp;

                $errors[$row['name']] = $error;
            }

        }

        return ['result' => count($errors) === 0, 'error' => $errors];
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
