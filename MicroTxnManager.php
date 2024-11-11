<?php
class MicroTxnManager extends Controller{

    public $apiKey = "*************";
    public $appId = 480;

    public function GetReport(){
        $params = [
            "key"=>$this->apiKey,
            "appid"=>$this->appId,
            "type"=>"GAMESALES",
            "time"=>"2024-11-04T00:00:00Z",
            "maxresults"=>1
        ];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://partner.steam-api.com/ISteamMicroTxn/GetReport/v5/?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);


        $result = curl_exec($ch);
        curl_close($ch);
         echo $result;
    }

    public function start($steamId){
        $currentGameLanguage = "en";
        $pack_id = $_GET["packId"];

        $userInfo = $this->getUserInfo($steamId);

        if($userInfo->response->result =="OK"){
            if( $userInfo->response->params->status != "Locked from purchasing"){
                $initTxn = $this->InitTxn($steamId, $currentGameLanguage,$pack_id);
                if( $initTxn->response->result == "OK"){

                    $microTxn = new MicroTxnModel("orders");
                    $microTxn->update(["trans_id"=>$initTxn->response->params->transid, "status"=>"initialize"])->where(["id"=>$initTxn->response->params->orderid])->run(true);
                    echo "Initialize";
                }else{
                    $orders = new MicroTxnModel("orders");
                    $orders->update(["status"=>"error"])->where(["id"=>$initTxn->response->params->orderid])->run(true);
                    echo $initTxn->response->error->errordesc ;
                    $this->start($steamId);
                }
            }
        }
    }

    public function finalize(){
        $order_id = $_GET["order_id"];
        $status = $_GET["status"];
        $orders = new MicroTxnModel("orders");
        if( $status == 1 ){
            $result = $this->finalizeTxn($order_id);
            if($result->response->result == "OK"){
                
                $orders->update(["status"=>"final"])->where(["id"=>$order_id])->run(true);
                $orders2 = new MicroTxnModel("orders");
                $pack_id = $orders2->select("`pack_id`")->where(["id"=>$order_id])->run(true);

                echo json_encode(["result"=>true, "pack_id"=>$pack_id["pack_id"]]);
            }else{
                echo json_encode(["result"=>FALSE]);
            }
        }else{
            $orders->update(["status"=>"cancel"])->where(["id"=>$order_id])->run(true);
            echo json_encode(["result"=>FALSE]);;
        }
    }

    private function InitTxn($steamId, $currentGameLanguage,$pack_id){
        $data = [
            "key"=>$this->apiKey,
            "orderid"=>$this->generateOrder($steamId, $pack_id),
            "steamid"=>$steamId,
            "appid"=>$this->appId,
            "itemcount"=>1,
            "language"=>$currentGameLanguage,
            "currency"=>"USD",
            "itemid[0]"=>$pack_id,
            "qty[0]"=>1,
            "amount[0]"=>$this->getPackPrice($pack_id),
            "description[0]"=>"A pack containing 3 cards of different rarity",
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://partner.steam-api.com/ISteamMicroTxn/InitTxn/v3/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, false);
    }

    private function generateOrder($steam_id, $pack_id){
        $orders = new MicroTxnModel("orders");

        return $orders->insertGetId(["steam_id"=>$steam_id,"pack_id"=>$pack_id, "status"=>"created"]);
    }

    private function getPackPrice($pack_id){
        $packs = new MicroTxnModel();
        $pack = $packs->getFromId($pack_id);
        return $pack["price"];
    }

    
    private function finalizeTxn($order_id){
        $data = [
            "key"=> $this->apiKey,
            "orderid"=> $order_id,
            "appid"=>$this->appId
        ];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://partner.steam-api.com/ISteamMicroTxn/FinalizeTxn/v2/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, false);
    }

    private function getUserInfo($steamId){
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, "https://partner.steam-api.com/ISteamMicroTxn/GetUserInfo/v2/?appid=".$this->appId."&steamid=$steamId&key=".$this->apiKey );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        $data = curl_exec( $ch );
        curl_close( $ch );

        return json_decode($data, false);
    }
}