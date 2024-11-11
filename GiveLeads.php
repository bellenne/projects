<?
$optNew = new OptNew();

$optNew->giveLeads();

Class OptNew {

    public $leadsInPipeline = 3;
    public $access_token = '***********';


   private function getLeads($status){
        $headers = ['Authorization: Bearer ' . $this->access_token];   
        
        $link = "https://3doboi.amocrm.ru/api/v4/leads?filter[statuses][0][pipeline_id]=569926&filter[statuses][0][status_id]=$status"; 

        $curl = curl_init(); 
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); 
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;

        try{
            
            return json_decode($out, true);

        } catch(\Exception $e){
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }
   }

    private function editLead($link, $params){
        $headers = ['Authorization: Bearer ' . $this->access_token];
        $curl = curl_init(); 
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;
        if ($code < 200 || $code > 204) {
            return "Error";
        }

        return json_decode($out, true);
    }


   public function giveLeads(){
        $leadsInSmena = $this->getLeads("*****");
        $leadsInPeredano = $this->getLeads("*****");
        if($leadsInSmena == null || count($leadsInSmena["_embedded"]["leads"]) < $this->leadsInPipeline ){
            $needleLeads = $this->leadsInPipeline - count($leadsInSmena["_embedded"]["leads"]);
            if($leadsInPeredano != null && $needleLeads > 0){
                $leadsForGiven = [];
                for($i=0; $i<$needleLeads; $i++){
                    foreach ($leadsInPeredano["_embedded"]["leads"] as $key => $lead) {
                        foreach ($lead["custom_fields_values"] as $customField) {
                            if($customField["field_id"]=="*****"){
                                if($customField["values"][0]["enum_id"]=="*****"){
                                    array_push($leadsForGiven, $lead);
                                    unset($leadsInPeredano["_embedded"]["leads"][$key]);
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if(count($leadsForGiven) < $needleLeads){
                    $missingLeads = $needleLeads - count($leadsForGiven);

                    for($i=0; $i<$missingLeads; $i++){
                        foreach ($leadsInPeredano["_embedded"]["leads"] as $key => $lead) {
                            array_push($leadsForGiven, $lead);
                            unset($leadsInPeredano["_embedded"]["leads"][$key]);
                            break;
                        }
                    }
                }

                foreach ($leadsForGiven as $lead) {
                    $data = json_encode(["status_id"=>"*****"]);
                    $link = "https://3doboi.amocrm.ru/api/v4/leads/".$lead["id"]; 
                    $this->editLead($link, $data);
                }

            }
        }
   }
}