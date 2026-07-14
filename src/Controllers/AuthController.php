<?php
    namespace App\Controllers;

    use App\Core\BaseController;

    class AuthController extends BaseController {
        public function __construct() {
            parent::__construct();
        }
        public function index() {
            $this->renderMainLogin();
        }
        public function renderAlreadyLoggedIn() {
      
            echo $this->render('already_logged_in.twig',[
                'user'=>$_SESSION['user']
            ]
            );
        }    
        private function renderMainLogin() {
            echo $this->render('login.twig',['page'=>'main']);
        }
    }