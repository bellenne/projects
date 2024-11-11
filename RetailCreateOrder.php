<?
require_once 'SendMail.php';
require_once 'CreateTicket.php';
require_once '../GoogleSheets.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Request-Method: OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');
header("Content-type: application/json; charset=utf-8");
$rco = new RetailCreateOrder();


http_response_code(200);
echo $rco->createOrder($_POST);

class RetailCreateOrder{
    private $DOMAIN = "*******";
    private $APIKEY = "*******";
    // private 
    private function validateData($data){
        if($data["firstname"] === "" || $data["product_id"] === "" || $data["lastname"] === "" || $data["surname"] === "" || $data["userPhone"] === "" || $data["email"] === "" || $data["product"] === "") return false;

        return true;
    }

    private function createParams($form){
        $tables = new GoogleSheets('"*******"');
        $product = $tables->getProduct($form["product_id"]);
        $params = [
            "firstName"=>$form["firstname"], 
            "lastName"=>$form["lastname"], 
            "orderMethod"=>"*******",
            "phone"=>$form["userPhone"],
            "email"=>$form["email"],
            "customerComment"=>"*******"
            ];
        return "order=".json_encode($params);
    }

    public function createOrder($form){
        $error = json_encode(["error"=>["code"=>400, "error_message"=>"Одно из полей не заполнено"]]);
        if(!$this->validateData($form)) return $error;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,'https://'.$this->DOMAIN.'.retailcrm.ru/api/v5/orders/create');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->createParams($form));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', "X-API-KEY: $this->APIKEY"));

        $html = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($html, false);
        if($result->success != true) return json_encode(["error"=>["code"=>500, "error_message"=>"Не удалось добавить заказ"]]);

        $createTicket = new CreateTicket();
        $nameSurname = $form["lastname"]." ".$form["firstname"];
        $imageUrl = $createTicket->createTicket($nameSurname, $form["surname"] , $result->id);
        
        
        $mail = new SendMail();
        $mailResult = $mail->sendMail($form["email"], $imageUrl);
        
        return $mailResult;

    }

    private function updateOrder($id, $product){
        $params = [
            "by"=>"Id",
            "customerComment"=>"*******"
        ];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,'https://'.$this->DOMAIN.'.retailcrm.ru/api/v5/orders/'.$id.'/edit');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "order=".json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', "X-API-KEY: $this->APIKEY"));

        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }
}