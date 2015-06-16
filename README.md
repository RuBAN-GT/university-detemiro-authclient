# Клиент для работы с сервером аутентификации ВГУ #

Данный клиент представляет собой класс, реализующий основные необходимые функции для работы с сервером аутентификации auth.vsu.ru.

## Требования к серверу ##

* PHP 5.3
* CURL

## Об аутентификации ##

Для того, чтобы правильно работать с данным классом и успешно провести аутентификацию необходимо понимать, что происходит при вызове методов.

**Рассмотрим простое демо:**
~~~~php
/**
 * 1.0. Создание объекта, имеющего информацию о вашем севисе
 */
$service = new \vsu\authClient(array(
    'service'  => 'test',
    'url'      => 'https://test.ru',
    'redirect' => 'https://test.ru/secure'
));

/**
 * 1.5. Проверка ответного токена для сбора данных
 */
if(isset($_GET['authToken'])) {
    /**
     * 4.0. Попытка получения данных по токену
     */
    if($res = $service->getByToken($_GET['authToken'])) {
        /**
         * 4.5. В случае успеха $res['data'] будет иметь результат
         */
        if($res['data']) {
            var_dump($res['data']);
        }
    }
}
else {
    /**
     * 2.0. Подготовка сессии
     */
    $prepare = $service->prepareSession();

    /**
     * 3.0. В случае успешной подготовки перенаправляем пользователя на страницу аутентификации
     */
    if($prepare && $prepare['data']) {
        $service->authentication($prepare['data']);
    }
}
~~~~

### 1.0. Описание сервиса ###

Первым шагом необходимо создать объект класса `\vsu\authClient` или его наследника, указав в конструктор ассоциативный массив,
содержащий информацию о вашем сервиса, а именно:

Ключ       | Пояснение
---------  | -----------------------------------------------------------------------------------------------------------------------------
`service`  | Имя вашего сервиса, это код на латинице, идентифицирующий ваше приложение. Данный код согласовывается с администратором УИЦ.
`url`      | URL адрес вашего приложения
`redirect` | Обратная ссылка на ваше приложение для возвращения пользователя после успешной аутентификации.

### 1.5. Проверка ответного токена ###

Данный станется возможным после успеха пункта 3.0.

## 2.0. Подготовка временной сессии ###

Чтобы сервер аутентификации знал, с каких приложением он будет работать, необходимо сообщить заранее методом POST информацию, переданную конструктору в виде JSON-строке. 
Это и делает за вас методом `prepareSession`.

В случае успешной проверки, методом вернёт массив, содержащий информацию о процессе проверки в полях `notices` и `errors`,
а также временный токен (ключ/пароль) для аутентификации в поле `data`.

### 3.0. Редирект на страницу аутентификации ###

Если вы получили временный токен, то теперь вы можете отправить пользователя на страницу аутентификации с помощью метода `authentication`, указав в нём этот временный токен.

Если пользователь успешно пройдёт аутентификацию, он будет перенаправлен на страницу, указанную в поле `redirect` с дополнительным URl-параметром `authToken`.

### 4.0. Получение данных по токену ###

Теперь, имея токен аутентификации, ваше приложение может получить данные по пользователю с помощью метода `getByToken`, указав в качестве аргумента этот токен.

### 4.5. Обработка данных ###

Если токен верен, то в ответе сервера в поле `data` будет содержаться информацию по пользователю в рамках используемого сервиса.

### Примечания ###

* Временный токен существует 2 часа.
* Для большенство сервисов постоянный токен также существует 2 часа, поэтому желательно кешировать (сохранять) результаты полученных данных на стороне приложения.
* При POST-запросах, с правильной информацией о клиенте, сервер возвращает массив в виде JSON-строки, содержащей следующие ключи:
 * data    - целевые данные вашей операции, например, временный токен или данные пользователя;
 * errors  - массив ошибок;
 * notices - массив дополнительной информации, например, об успехе получения данных;
* При неправильном указании информации о вашем приложении (сервисе), сервер возвращает также возвращает массив с сообщениями в `errors`.
* При пустом или неверном запросе сервер перенаправляет или запрос, или пользователя на 404 страницу.

## vsu\authClient ##

Данный класс содержит следующие методы.

### static getCurrentURL($scheme = true, $host = true, $path = true) ###

Получение текущей ссылки. 
Аргументы:

* bool - $scheme - Отображение протокола подключения
* bool - $host   - Отображения хоста
* bool - $path   - Отображение URI

Результат: string.

### static sendRequest($url, $body = null, $type = 'POST', $agent = 'authVSU') ###

Данный метод осуществляет отправку запроса типа POST, GET, PUT, DELETE с аргументами под выбранным агентом. 
Для его работы необходима библиотека curl. 
Аргументы:

* string       - $url   - URL получателя (если вы используете метод GET, укажите параметры или в $url, или в $body)
* array|object - $body  - Тело запроса
* string       - $type  - Тип запроса (POST, GET, PUT, DELETE)
* string       - $agent - Агент

Результат: string

### static redirect($link) ###

Данный метод осуществляет редирект на $link.

Аргументы: string $link Ссылка

### static jsonEncode($obj) ###

Корректное кодирование $obj в JSON (в строку Unicode).

### static jsonDecodeStruct($str, $assoc = true) ###

Декодирование JSON-структур
Аргументы: 

* string - $str   - JSON-строка
* bool   - $assoc - Результат будет преобразован в ассоц. массив, в противном случае - в объект

### serverRequest($page, array $data) ###

Запрос к серверу аутентификации.

В случае успешного запроса результатом будет являться спарсенный результат. В противном случае - false. 
Аргументы:

* string - $page - Внутренняя страница auth.vsu.ru
* array  - $data - Отправляемые данные

Результат: string|false

### prepareSession() ###

Подготовительный запрос для сессии

Результат: array|false.

### authentication($sessionToken) ###

Редирект на страницу аутентификации для пользователя с токенов временной сессии, полученным на prepareSession.


### getByToken($token) ###

Получение данных пользователя по токену

Результат: array|false.