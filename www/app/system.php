<?php

namespace App;

use App\Entity\User;

/**
 * Класс  содержащи  методы  работы   с  наиболее  важными
 * системными  данными
 */
class System
{
    public const CURR_VERSION = "6.12.0";
    public const PREV_VERSION = "6.11.9";
    public const REQUIRED_DB  = "6.12.0";

    private static $_options = array();   //  для кеширования

    private static $_cache   = array();   //  для кеширования

    /**
     * Возвращает  текущего  юзера
     * @return  User
     */
    public static function getUser() {
        $user = Session::getSession()->user;
        if ($user == null) {
            $user = new User();
            self::setUser($user);
        }
        return $user;
    }

    /**
     * Устанавливавет  текущего  юзера  в  системме
     *
     * @param User $user
     */
    public static function setUser(User $user) {
        Session::getSession()->user = $user;
    }

    public static function getBranch() {

        return intval(Session::getSession()->branch_id);
    }

    public static function setBranch(int $branch_id) {
        Session::getSession()->branch_id = $branch_id;
    }

    public static function getCustomer() {

        return (int)Session::getSession()->customer_id;
    }

    public static function setCustomer(int $customer_id) {
        Session::getSession()->customer_id = $customer_id;
    }

    /**
     * Возвращает  сессию
     * @return  Session
     */
    public static function getSession() {

        return Session::getSession();
    }

    /**
     * Возвращает набор  параметром  по  имени набора
     *
     * @param mixed $group
     * @param mixed $isserialise
     */
    public static function getOptions($group, $isserialise=true) {

        if (isset(self::$_options[$group])) {
            return self::$_options[$group];
        }
        $conn = \ZDB\DB::getConnect();

        $rs = $conn->GetOne("select optvalue from options where optname='{$group}' ");
        if (strlen($rs) > 0) {
            if(!$isserialise) {
                self::$_options[$group] = $rs;
                return $rs;
            }  //неупакопано

            $d =    @unserialize(@base64_decode($rs));
            if(!is_array($d)) {
                $d =  @unserialize($rs); //для  совместивости
            }
            self::$_options[$group] = $d;
        }

        return self::$_options[$group] ?? [];
    }

    /**
     * возвращает настройку
     *
     * @param mixed $group
     * @param mixed $option
     */
    public static function getOption($group, $option) {

        $options = self::getOptions($group);

        return $options[$option];
    }

    /**
     * Записывает набор  параметров  по имени набора
     *
     * @param mixed $group
     * @param mixed $options
     */
    public static function setOptions($group, $options) {
        self::$_options[$group] = $options;
        $options = serialize($options);
        $options = base64_encode($options) ;
        $conn = \ZDB\DB::getConnect();
        $conn->Execute(" delete from options where  optname='{$group}' ");
        $conn->Execute(" insert into options (optname,optvalue) values ('{$group}'," . $conn->qstr($options) . " ) ");
    }
    /**
    * установить отьедный параметр
    *
    * @param mixed $group
    * @param mixed $option
    * @param mixed $value
    */
    public static function setOption($group, $option, $value) {

        $options = self::getOptions($group);
        $options[$option]  = $value;

        self::setOptions($group, $options) ;
    }

    public static function setCache($key, $data) {
        self::$_cache[$key] = $data;
    }

    public static function getCache($key) {

        if (isset(self::$_cache[$key])) {
            return self::$_cache[$key];
        }
        return null;
    }

    public static function setSuccessMsg($msg) {
        Session::getSession()->smsg = $msg;
    }

    public static function getSuccesMsg() {
        return Session::getSession()->smsg;
    }

    public static function setErrorMsg($msg, $toppage=false) {
        if($toppage) {
            Session::getSession()->emsgtp = $msg;
        } else {
            Session::getSession()->emsg = $msg;
        }
    }

    public static function getErrorMsg() {
        return Session::getSession()->emsg;
    }

    public static function getErrorMsgTopPage() {
        return Session::getSession()->emsgtp;
    }

    public static function setWarnMsg($msg) {
        Session::getSession()->wmsg = $msg;
    }

    public static function getWarnMsg() {
        return Session::getSession()->wmsg;
    }

    public static function setInfoMsg($msg, $toppage=false) {

        if($toppage) {
            Session::getSession()->imsgtp = $msg;
        } else {
            Session::getSession()->imsg = $msg;
        }
    }

    public static function getInfoMsgTopPage() {
        return Session::getSession()->imsgtp;
    }
   
    public static function getInfoMsg() {
        return Session::getSession()->imsg;
    }

    public static function clean() {
        self::$_cache = [] ;

    }

    public static function useCron() {
        return  \App\Helper::getKeyVal('cron') ?? false;
    }
  
  /**
  * проверка  на  входязий IP
  * 
  */
    public static function checkIP() {
        $options=self::getOptions('common') ;
        if($options['checkip']  != 1) return;
        if($_SERVER['REMOTE_ADDR']=='127.0.0.1')  return;
        
        $list = explode("\n",$options['iplist'] ) ;
        foreach($list as $ip) {
            if(trim($ip)=== $_SERVER['REMOTE_ADDR']) {
                return;
            }
        }

        http_response_code(403) ;
        die;
    }

    
    /**
    * проверка  обновлений  и ряа  параметров  настроек
    * 
    */
    public static function checkUpdate() {
        $options = System::getOptions("common");       
        if($options['noupdate']==1) {
           return;  
        }
        $lastcheck=intval( \App\Helper::getKeyVal('lastchecksystem')) ;
        if(strtotime('-7 day') < $lastcheck ) {
            return;
        }
     
        \App\Helper::setKeyVal('lastchecksystem',time()) ;
       
        $user = System::getUser() ;
        if ($user->userlogin == "admin") {
                if ($user->userpass == "admin" || $user->userpass == '$2y$10$GsjC.thVpQAPMQMO6b4Ma.olbIFr2KMGFz12l5/wnmxI1PEqRDQf.') {
                    $n = new \App\Entity\Notify();
                    $n->user_id = $user->user_id;
                    $n->message = "Змініть у профілі пароль за замовчуванням " ;
                    $n->sender_id = \App\Entity\Notify::SYSTEM;

                    $n->save();                 
                          
                }
        }
         
        if($user->rolename=='admins'   ){
          
            if (\App\Entity\Notify::isNotify(\App\Entity\Notify::SYSTEM)) {
                $n = new \App\Entity\Notify();
                $n->user_id = $user->user_id;
                $n->message = "Є непрочитані системні повідомлення " ;
                $n->sender_id = \App\Entity\Notify::SYSTEM;

                $n->save();                 
            }          
          
             
            $b=0;
            $phpv =   phpversion()  ;

            $v = @file_get_contents("https://zippy.com.ua/version.json" );
            $data = @json_decode($v, true);
            if(is_array($data)){
               $b= version_compare($data['version'] , System::CURR_VERSION);
            }               
                 
            if( $b==1 ){
                $n = new \App\Entity\Notify();
                $n->user_id = $user->user_id;
                $n->message = "Доступна  нова  версія <b>{$data['version']}</b>. <a href=\"/index.php?p=App/Pages/Update\">Детальнішк</a>" ;
                $n->sender_id = \App\Entity\Notify::SYSTEM;

                $n->save();              
            }                                                     
        }        
    }

}
