<?php
    namespace auth;

    class authClient {
        /**
         * Адрес сервера аутентификации
         *
         * @var string
         */
        protected $domain = 'https://auth.vsu.ru';
        public function domain() {
            return $this->domain;
        }

        /**
         * Конфигурация сервиса
         *
         * @var array
         */
        protected $config = array(
            'service'  => '',
            'secret'   => '',
            'redirect' => '',
            'url'      => ''
        );

        /**
         * Получение конфигурации
         *
         * @return array
         */
        public function config() {
            return $this->config;
        }

        /**
         * Конструктор
         *
         * В конструктор необходимо добавить ассоциативный массив со следующими ключами:
         * Ключ     | Пояснение
         * -------- | ----------
         * service  | Имя сервиса, уточняется у администратора
         * secret   | Секретный ключ сервиса
         * redirect | Обратная ссылка для редиректа с сервера аутентификации
         * url      | URL приложения
         *
         * @param array  $config Конфигурация клиентского сераиса
         * @param string $domain Адрес сервера аутентификации
         *
         * @throws \Exception Если неверно указаны параметры сервиса.
         */
        public function __construct(array $config, $domain = '') {
            $this->config = array_replace_recursive($this->config, $config);

            if($this->config['service'] == '' || is_string($this->config['service']) == false) {
                throw new \Exception('Необходимо указать имя сервиса.');
            }

            if(is_string($this->config['secret'] == false)) {
                throw new \Exception('Ключ сервиса указан неверно.');
            }

            if($this->config['redirect'] == '' || is_string($this->config['redirect'] == false)) {
                throw new \Exception('Не указана обратная ссылка.');
            }

            if($domain && is_string($domain)) {
                $this->domain = $domain;
            }
            elseif($this->domain == null) {
                throw new \Exception('Не указан адрес сервера аутентификации');
            }
        }

        /**
         * Получение текущей ссылки
         *
         * @param  bool $scheme Отображение протокола подключения
         * @param  bool $host   Отображения хоста
         * @param  bool $path   Отображение URI
         *
         * @return string
         */
        public static function getCurrentURL($scheme = true, $host = true, $path = true) {
            $current = '';

            if($scheme && isset($_SERVER['REQUEST_SCHEME'])) {
                $current = $_SERVER['REQUEST_SCHEME'] . '://';
            }
            if($host) {
                if(isset($_SERVER['HTTP_HOST'])) {
                    $pos = strrpos($_SERVER['HTTP_HOST'], ':');

                    if($pos !== false) {
                        $current .= substr($_SERVER['HTTP_HOST'], 0, $pos);
                    }
                    else {
                        $current .= $_SERVER['HTTP_HOST'];
                    }
                }
                elseif(isset($_SERVER['SERVER_NAME'])) {
                    $current .= $_SERVER['SERVER_NAME'];
                }
                elseif(isset($_SERVER['SERVER_ADDR'])) {
                    $current .= $_SERVER['SERVER_ADDR'];
                }
            }
            if($path && isset($_SERVER['REQUEST_URI'])) {
                if($parse = parse_url($_SERVER['REQUEST_URI'])) {
                    if(isset($parse['path'])) {
                        $current .= rtrim($parse['path'], '/');
                    }
                }
            }

            return $current;
        }

        /**
         * Отправка запроса
         *
         * Данный метод осуществляет отправку запроса типа POST, GET, PUT, DELETE с аргументами под выбранным агентом.
         * Для его работы необходима библиотека curl.
         *
         * @see curl_init()
         *
         * @param  string       $url   URL получателя (если вы используете метод GET, укажите параметры или в $url, или в $body)
         * @param  array|object $body  Тело запроса
         * @param  string       $type  Тип запроса (POST, GET, PUT, DELETE)
         * @param  string       $agent Агент
         *
         * @return mixed
         */
        public static function sendRequest($url, $body = null, $type = 'POST', $agent = 'authClient') {
            if(
                function_exists('curl_version') &&
                is_string($url) && is_string($agent) &&
                in_array($type, array('POST', 'GET', 'PUT', 'DELETE'))
            ) {
                $ch = curl_init();

                if($body) {
                    if(is_array($body) || is_object($body)) {
                        $body = http_build_query($body);
                    }
                    elseif(self::jsonDecodeStruct($body)) {
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($body)
                        ));
                    }
                }

                if($type == 'GET' && $body) {
                    $url .= (parse_url($url, PHP_URL_QUERY)) ? '&' : '?';
                    $url .= $body;
                }

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                if(ini_get('open_basedir') === '') {
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                }

                curl_setopt($ch, CURLOPT_USERAGENT, $agent);

                if($type != 'GET') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
                }

                if($type != 'GET' && $body) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }

                $res = curl_exec($ch);

                curl_close($ch);

                return $res;
            }
            else {
                return null;
            }
        }

        /**
         * Редирект
         *
         * Данный метод осуществляет редирект на $link.
         *
         * @param  string $link
         *
         * @return void
         */
        public static function redirect($link) {
            @header("Location: $link");

            exit();
        }

        /**
         * Кодирование в JSON
         *
         * Корректное кодирование $obj в JSON (в строку Unicode).
         *
         * @param  mixed       $obj
         * @return string|null
         */
        public static function jsonEncode($obj) {
            $res = null;

            if(is_string($obj)) {
                $res = $obj;
            }
            else {
                try {
                    $res = json_encode($obj, JSON_UNESCAPED_UNICODE);
                }
                catch(\Exception $e) {
                    $res = null;
                }
            }

            return $res;
        }

        /**
         * Декодирование JSON-структур
         *
         * @param  string            $str
         * @param  bool              $assoc Результат будет преобразован в ассоц. массив
         * @return array|object|null
         */
        public static function jsonDecodeStruct($str, $assoc = true) {
            if($str && is_string($str) && is_numeric($str) == false) {
                $res = json_decode($str, $assoc);

                if(json_last_error() == JSON_ERROR_NONE) {
                    return $res;
                }
            }

            return null;
        }

        /**
         * Запрос к серверу аутентификации
         *
         * В случае успешного запроса результатом будет являться спарсенный результат.
         * В противном случае - false.
         *
         * @param       $page
         * @param array $data
         *
         * @return string|false
         */
        public function serverRequest($page, array $data) {
            if(is_string($page) && $page) {
                $res = self::sendRequest("{$this->domain}/$page", self::jsonEncode($data), 'POST');

                if($res) {
                    return $res;
                }
            }

            return false;
        }

        /**
         * Подготовительный запрос для сессии
         *
         * @return array|false
         */
        public function prepareSession() {
            if($res = $this->serverRequest('prepareSession', $this->config)) {
                if($res = self::jsonDecodeStruct($res)) {
                    return array_replace_recursive(array('data' => '', 'errors' => array(), 'notices' => array()), $res);
                }
            }

            return false;
        }

        /**
         * Редирект на страницу аутентификации.
         *
         * Редирект на страницу аутентификации для пользователя с токенов временной сессии, полученным из prepareSession.
         *
         * @param string $sessionToken
         *
         * @return void
         */
        public function authentication($sessionToken) {
            if(is_string($sessionToken) && $sessionToken) {
                self::redirect("{$this->domain}/authentication?sessionToken=$sessionToken");
            }
        }

        /**
         * Получение данных пользователя по токену
         *
         * @param $token
         *
         * @return array|bool
         */
        public function getByToken($token) {
            if(is_string($token) && $token) {
                $app = $this->config;

                $app['token'] = $token;

                if($res = $this->serverRequest('checkToken', $app)) {
                    if($res = self::jsonDecodeStruct($res)) {
                        $res = array_replace_recursive(array('data' => '', 'errors' => array(), 'notices' => array()), $res);

                        if($res['data'] && is_string($res['data'])) {
                            if($try = self::jsonDecodeStruct($res['data'])) {
                                $res['data'] = $try;
                            }
                        }

                        return $res;
                    }
                }
            }

            return false;
        }
    }
?>