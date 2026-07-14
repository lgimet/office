<?php
    namespace App\Helpers;
   
    class Response
    {
        // Constantes de statut
        public const SUCCESS = true;
        public const FAILURE = false;

        public const NONE = 0;
        public const EXPIRED = 1;
        public const INVALID = 2;
        // Propriétés principales
        private bool $success = true;
        private ?string $object = null;
        private ?string $subObject = null;
        private ?string $action = null;
        private array $payload = [];
        private ?array $error = null;
        private ?string $html = null;
        private ?string $id = null;
        private ?string $title = null;
        private ?string $toast = null;
        private ?string $redirect = null;
        private ?string $csrf = null;
        private ?string $page=null;
        private int $statusCode = 200;

        public function setObject(string $object): self
        {
            $this->object = $object;
            return $this;
        }
        public function setSubObject(?string $subObject): self {
            $this->subObject = $subObject;
            return $this;
        }
        public function setAction(string $action): self
        {
            $this->action = $action;
            return $this;
        }

        public function setPayload(array $payload): self
        {
            $this->payload = $payload;
            return $this;
        }
        public function setHtml(string $html): self {
            $this->html = $html;
            return $this;
        }
        public function setId($id) : self {
            $this->id = $id;
            return $this;
        }
        public function setTitle($title): self {
            $this->title = $title;
            return $this;
        }
        public function setToast($toast) : self {
            $this->toast = $toast;
            return $this;
        }
        public function setRedirect($redirect) : self {
            $this->redirect=$redirect;
            return $this;
        }
        public function setCsrf() : self {
            $this->csrf = Csrf::generate();
            return $this;
        }
        public function setPage($page) : self {
            $this->page = $page;
            return $this;
        }
        public function setError(int $code, string $message,int $underCode=0): self
        {
            $this->success = self::FAILURE;
            $this->statusCode = $code;
            $this->error = [
                'code' => $code,
                'underCode' => $underCode,
                'message' => $message
            ];
            return $this;
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function toJson(): string
        {
            return json_encode([
                'success' => $this->success,
                'object'  => $this->object,
                'subobject' => $this->subObject,
                'action'  => $this->action,
                'data' => $this->payload,
                'html' => $this->html,
                'id' => $this->id,
                'title' => $this->title,
                'error'   => $this->error,
                'toast' => $this->toast,
                'redirect' => $this->redirect,
                'csrf' => $this->csrf
            ]) ?: '{}';
        }

        public function send(): void
        {
            http_response_code($this->statusCode);

            if( $this->page ) {
                header('Content-Type: text/html; charset=utf-8');
                echo $this->page;
                return;
            }
            header('Content-Type: application/json');

            echo $this->toJson();
        }

    }

?>
