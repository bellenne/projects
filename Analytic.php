<?
require_once("Mpstat.php");
class Main{
    public function getData($dateStart, $dateEnd, $path, $isFbs, $filter_type, $filter_count){

        $mps = new Mpstat();
        $currentLastRow = 5000;
        $data = [];

        $subjects = json_decode($mps->getSubjects($dateStart, $dateEnd,$path, 0, $currentLastRow, $isFbs, $filter_type, $filter_count),true);
        array_push($data, $subjects["data"]);
        isEnd:
            if($subjects["total"] > $currentLastRow){

                $startRow = $currentLastRow;

                $difference = $subjects["total"] - $currentLastRow;

                if($difference > 5000) $currentLastRow += 5001;
                else $currentLastRow += $difference;
                $subjects = json_decode($mps->getSubjects($dateStart, $dateEnd,$path, $startRow,  $currentLastRow, $isFbs, $filter_type, $filter_count),true);

                array_push($data, $subjects["data"]);

                goto isEnd;
            }
            
            $timestamp = strtotime($dateEnd) - strtotime($dateStart) < 600000 ? false : true;
            if($path == 2261){
                 
                if($timestamp) $data = $this->clearFakePickSales($this->clearMetersVerol($this->clearEmptyBrands($this->correctArray($data))));
                else $data = $this->clearMetersVerol($this->clearEmptyBrands($this->correctArray($data)));
            }
            else {
                
                if($timestamp) $data = $this->clearFakePickSales($this->clearEmptyBrands($this->correctArray($data)));
                else $data = $this->clearEmptyBrands($this->correctArray($data));
            }
            
        return $data;
    }

    private function correctArray($array){
        $resultArray=[];
        foreach ($array as $nestedArray) {
            foreach ($nestedArray as $key => $value) {
                array_push($resultArray, $nestedArray[$key]);
            }
        }
        return $resultArray;
    }

    private function clearEmptyBrands($array){
        foreach ($array as $key => $value) {
            if($value["brand"] == "" || $value["brand"] == null){
                unset($array[$key]);
            }
        }
        array_values($array);
        return $array;
    }

    private function clearFakePickSales($array){
        $between = 2;    

        foreach ($array as $keyArr => $product) {
            foreach ($product["graph"] as $key => $value) {
                if($value > 20){
                    $betweenSum = 0;
                    for($i=0; $i<$between; $i++){
                        if(isset($product["graph"][$key-$between-$i])) $betweenSum+=$product["graph"][$key-$between-$i];
                        else $betweenSum+=$product["graph"][$key+$between+(-1*($key-$between-$i))];

                        if(isset($product["graph"][$key+$between-$i])) $betweenSum+=$product["graph"][$key+$between-$i];
                        else $betweenSum+=$product["graph"][$key-$between-(($key+$between-$i)-count($product["graph"])-1)];;
                    }

                    if($value > ceil($betweenSum/$between*2) * 2){
                        $summsales = 0;
                        $product["graph"][$key] = ceil($betweenSum/$between*2);
                        foreach ($product["graph"] as $salesOnDay) {
                            $summsales+=$salesOnDay;
                        }
                        $product["sales"] = $summsales;
                        $product["revenue"] = $product["client_price"]*$summsales;
                        
                        $array[$keyArr]["sales"] = $summsales;
                        $array[$keyArr]["revenue"] = $product["client_price"]*$summsales;

                    }
                    break;
                }
            }
        }
        return $array;
    }

    private function clearMetersVerol($array){
        foreach ($array as $key => $product) {
            if($product["brand"] == "VEROL" && str_contains($product["name"], "Метровые")) unset($array[$key]);
        }

        array_values($array);
        return $array;
    }


    private function getBrands($array){
        $brands = [];
        foreach ($array as $key => $product) {
            array_push($brands, $product["brand"]);
        }
        $brands = array_unique($brands);
        $brands = array_flip($brands);

        foreach ($brands as $key => $value) {
            $brands[$key] = 0;
        }

        return $brands;
    }

    public function getTopBrands($array){
        $uniqueBrands = $this->getBrands($array);
        
        foreach ($array as $key => $product) {
            $uniqueBrands[$product["brand"]] += $product["revenue"];
        }

        arsort($uniqueBrands);

        if(count($uniqueBrands) > 10) {
            $brands = [];
            $i = 1;
            foreach ($uniqueBrands as $key => $value) {
                if($i<11){
                    $brands[$key] = $value;
                    $i++;
                }else break;
            }

            if(!in_array("SEBRICCI", $brands)) $brands["SEBRICCI"] = $uniqueBrands["SEBRICCI"];

            return $brands;
        }else return $uniqueBrands;

        
    }
    
    private function getProductFromBrand($array, $brand){
        $result = [];
        foreach ($array as $key => $product) {
            if($product["brand"] == $brand) array_push($result, $product);
        }
        
        uasort($result, "cmp");
        return $result;
    }

    public function getTopProduct($array){
        $topProduct = [];
        $topBrands = $this->getTopBrands($array);

        foreach ($topBrands as $key => $value) {
            $topProduct[$key] = [];
            $allProductsFromBrand = $this->getProductFromBrand($array, $key);
            for($i=0; $i<10; $i++){
                array_push($topProduct[$key], $allProductsFromBrand[$i]);
            }

        }
        return $topProduct; 
    }
}
function cmp($a, $b) {
    if ($a["revenue"] == $b["revenue"]) {
        return 0;
    }
    return ($a["revenue"] < $b["revenue"]) ? 1 : -1 ;
}