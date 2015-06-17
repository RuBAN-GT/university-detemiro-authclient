<?php
    include('authClient.php');

    /**
     * 1.0. Создание объекта, имеющего информацию о вашем севисе
     */
    $service = new \vsu\authClient(array(
        'service'  => 'test',
        'url'      => 'https://test.ru',
        'redirect' => 'https://test.ru/secure'
    ));

    /**
     * 4.0. Попытка получения данных по токену
     */
    if(isset($_GET['authToken'])) {

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
?>