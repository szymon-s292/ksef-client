<?php

class HttpRequest {
    public $url;
    public $method;
    public $headers = [];
    public $body = null;
    
    public function __construct($url, $method = 'GET', $headers = [], $body = null) {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->body = $body;
    }
}

class HttpResponse {
    public $statusCode;
    public $headers;
    public $body;

    public function __construct($statusCode, $headers, $body) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }
}

class Http {
    public static function send(HttpRequest $request): HttpResponse {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request->headers);

        if (!empty($request->body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($request->body) ? http_build_query($request->body) : $request->body);
        }

        $responseBody = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        $statusCode = $responseInfo['http_code'];
        curl_close($ch);

        return new HttpResponse($statusCode, [], $responseBody);
    }

    public static function get($url, $headers = []) {
        return self::send(new HttpRequest($url, 'GET', $headers));
    }

    public static function post($url, $body = null, $headers = []) {
        return self::send(new HttpRequest($url, 'POST', $headers, $body));
    }

    public static function put($url, $body = null, $headers = []) {
        return self::send(new HttpRequest($url, 'PUT', $headers, $body));
    }

    public static function delete($url, $headers = []) {
        return self::send(new HttpRequest($url, 'DELETE', $headers));
    }
}

?>
