<?php

/***
 *  调用同城开放API的SDK类库
 *  add by Shi Bin
***/
class Tongcheng {
    
    private $server_url = 'http://tcopenapi.17usoft.com/handlers/scenery/queryhandler.ashx'; //接口地址调用正式的url
    private $version = '20111128102912'; //接口协议版本号，详见接口协议文档
    private $accountID = ''; //API帐户ID(小写)，待申请审批通过后发
    private $accountKey = ''; //API帐户密钥，待申请审批通过后发放
        
    private function get_digitalSign($serviceName, $reqTime){
        $arr = array(
                'Version'=>$this->version,
                'AccountID' => $this->accountID,      
                'ServiceName' => $serviceName,
                'ReqTime' => $reqTime
            );
        ksort($arr);
        reset($arr);
        $sort_array  = $arr;
        $arg  = "";
        while (list ($key, $val) = each ($arr)) {
            $arg.=$key."=".$val."&";
        }
        $arg = substr($arg,0,count($arg)-2); //去掉最后一个&字符
        return md5($arg.$accountKey); //数字签名
    }
    
    private function get_xml_data($serviceName, $parm){
        $currentMS = (int)(microtime()*1000);
        $reqTime = date("Y-m-d H:i:s").".".$currentMS;
        $digitalSign = $this->get_digitalSign($serviceName, $reqTime);
        $xml_data = '<?xml version="1.0" encoding="utf-8"?>
            <request>
                <header>
                    <version>'.$this->version.'</version>
                    <accountID>'.$this->accountID.'</accountID>   
                    <serviceName>'.$serviceName.'</serviceName>
                    <digitalSign>'.$digitalSign.'</digitalSign>
                    <reqTime>'.$reqTime.'</reqTime>
                </header>
                <body>'.$parm.'</body>
            </request>';
        return $xml_data;
    }
    
    private function get_response($xml_data){
        $header = array();
        $header[] = "Content-type: text/xml";	//定义content-type为xml
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        
        $ob= simplexml_load_string($response);
        $return_array = json_decode(json_encode($ob), true);
        if($return_array["header"]["rspType"] == 1){
            return false;
        }
        if($return_array["header"]["rspType"] == 0){
            return $return_array["body"];
        }
    } 
    
    /*
     * 获取省列表
     * 默认返回所有中国省份的Id和名称列表
     */
    
    public function GetProvinceList(){
        $xml_data = $this->get_xml_data("GetProvinceList");
        return $this->get_response($xml_data);
    }
    
    /*
     * 查询城市列表
     * 根据省份Id获取该省下所有地级市,省份Id可由[获取省份列表]获取
     * provinceId 省ID 可由[获取省份列表]接口获取
     */
    
    public function GetCityListByProvinceId($provinceId){
        $parm = "<provinceId>".$provinceId."</provinceId>";
        $xml_data = $this->get_xml_data("GetCityListByProvinceId", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 查询行政区列表
     * 根据城市Id获取城市下的所有行政区,城市Id可由[查询城市列表]接口获取
     * cityId 城市ID 要查询的城市的ID可由[查询城市列表]接口获取
     */
    
    public function GetCountyListByCityId($cityId){
        $parm = "<cityId>".$cityId."</cityId>";
        $xml_data = $this->get_xml_data("GetCountyListByCityId", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 根据名称查询区划信息
     * 根据行政区名称,获取行政区详细信息 
     * divisionName 区划名称 支持县级市，例如“昆山”
     */
    
    public function GetDivisionInfoByName($divisionName){
        $parm = "<divisionName>".$divisionName."</divisionName>";
        $xml_data = $this->get_xml_data("GetDivisionInfoByName", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 根据ID查询区划信息
     * 根据行政区Id,获取行政区详细信息 
     * divisionId 区划Id	
     */
    
    public function GetDivisionInfoById($divisionId){
        $parm = "<divisionId>".$divisionId."</divisionId>";
        $xml_data = $this->get_xml_data("GetDivisionInfoById", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 获取景点列表
     * 根据输入的条件搜索符合条件的景区返回列表,用于景区搜索页
     * page 页数	不传默认为1
     * pageSize	分页大小 不传默认为10，最大为100
     * cityId 城市Id 根据城市Id查景点参数
     * sortType	排序类型 0-点评由多到少 1-景区级别 2-同程推荐 3-人气指数 4-按距离升序 默认按同程推荐排序
     * keyword 搜索关键词 用于模糊搜索
     * searchFields 搜索字段 当有keyword时必传入，多个用英文逗号隔开如：field1,field2 有以下字段:city 城市; name; nameAlias 别名; namePYLC 景区拼音(全拼); nameTKPY  景区名分词全拼; nameTKFPY 景区名分词首字母; summary; themeName 主题; 未指定的话按默认定的字段搜索
     * gradeId 星级Id 如1,2,3,4,5，可传多个，以英文逗号隔开
     * themeId 主题Id 如1,2,3,4,5，可传多个，以英文逗号隔开
     * priceRange 价格范围 如0,50，表示0到50
     * cs 坐标系统 1.mapbar 2.百度；不传默认为1
     * latitude 纬度 如:128.12314
     * longitude 经度 如:32.123015
     * radius 半径 有经纬度时必传,单位:米
     * provinceId 省份Id 根据省份查询
     * countryId 行政区划（县）id 根据行政区划id
     * payType 支付类型 1 在线支付 2 景区现付
     */
    
    public function GetSceneryList($provinceId = "", $cityId = "", $countryId = "", $page = 1, $pageSize = 100, $sortType = "", $keyword = "", $searchFields = "", $gradeId = "", $themeId = "", $priceRange = "", $cs = "", $longitude = "", $latitude = "", $radius = "", $payType = ""){
        $clientIp = "127.0.0.1";
        $parm = '<clientIp>'.$clientIp.'</clientIp>
                <provinceId>'.$provinceId.'</provinceId>
                <cityId>'.$cityId.'</cityId>
                <countryId>'.$countryId.'</countryId>
                <page>'.$page.'</page>
                <pageSize>'.$pageSize.'</pageSize>
                <sortType>'.$sortType.'</sortType>
                <keyword>'.$keyword.'</keyword>
                <searchFields>'.$searchFields.'</searchFields>
                <gradeId>'.$gradeId.'</gradeId>
                <themeId>'.$themeId.'</themeId>
                <priceRange>'.$priceRange.'</priceRange>
                <cs>'.$cs.'</cs>
                <longitude>'.$longitude.'</longitude>
                <latitude>'.$latitude.'</latitude>
                <radius>'.$radius.'</radius>
                <payType>'.$payType .'</payType>';
        $xml_data = $this->get_xml_data("GetSceneryList", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 获取景点详细信息
     * 根据景区Id查询景区详情,景区Id根据[获取景区列表]等接口得到
     * sceneryId 景点Id 可根据[获取景区列表]得到
     * cs 坐标系统 1.mapbar 2.百度；不传默认为1
     */
    
    public function GetSceneryDetail($sceneryId, $cs = ""){
        $parm = '<sceneryId>'.$sceneryId.'</sceneryId><cs>'.$cs.'</cs>';
        $xml_data = $this->get_xml_data("GetSceneryDetail", $parm);
        return $this->get_response($xml_data);
    }
    
    /*
     * 获取景点交通指南信息
     * 根据景区Id查询景区交通指南信息,景区Id根据[获取景区列表]等接口得到
     * sceneryId 景点Id	
     */
    
    public function GetSceneryTrafficInfo($sceneryId){
        $parm = '<sceneryId>'.$sceneryId.'</sceneryId>';
        $xml_data = $this->get_xml_data("GetSceneryTrafficInfo", $parm);
        return $this->get_response($xml_data);        
    }
    
    /*
     * 获取景点图片列表
     * 根据景区Id查询景点图片列表,景区Id根据[获取景区列表]等接口得到
     * sceneryId 景点Id
     * page 页码
     * pageSize 页面大小			
     */
    
    public function GetSceneryImageList($sceneryId, $page = "", $pageSize = ""){
        $parm = '<sceneryId>'.$sceneryId.'</sceneryId><page>'.$page.'</page><pageSize>'.$pageSize.'</pageSize>';
        $xml_data = $this->get_xml_data("GetSceneryImageList", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 获取周边景点
     * 根据景区Id查询景点周边热门景点列表,景区Id根据[获取景区列表]等接口得到
     * sceneryId 景点Id
     * page 页码	默认为1
     * pageSize	页面大小 默认为10
     */
    
    public function GetNearbyScenery($sceneryId, $page = "", $pageSize = ""){
        $parm = '<sceneryId>'.$sceneryId.'</sceneryId><page>'.$page.'</page><pageSize>'.$pageSize.'</pageSize>';
        $xml_data = $this->get_xml_data("GetNearbyScenery", $parm);
        return $this->get_response($xml_data); 
    }
    /*
     * 获取价格搜索接口
     * 根据景区Id,获取价格搜索接口
     * showDetail 影响返回内容节点数量 1、简单 2、详细 默认为1
     * sceneryIds 景区id 可以传入多个逗号分隔，必填。 示例：3440,1360,79,…，一次最多20个
     * payType 支付方式 0-所有，1-到付，2-在线支付
     * useCache	是否使用缓存 0:不使用 1使用;默认为0
     */
    
    public function GetSceneryPrice($sceneryIds, $showDetail = "", $payType = "", $useCache = ""){
        $parm = '<showDetail>'.$showDetail.'</showDetail><sceneryIds>'.$sceneryIds.'</sceneryIds><payType>'.$payType.'</payType><useCache>'.$useCache.'</useCache>';
        $xml_data = $this->get_xml_data("GetSceneryPrice", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 获取价格日历接口
     * 根据景点价格政策id 来获取价格搜索接口
     * policyId 价格id 如:102
     * startDate 查询开始日期 格式:2012-07-01 传入是本月日期，查询明天到该日期的数据;大于本月的日期，查询该月的所有数据
     * endDate 查询结束日期 目前此字段无效,暂时知道就行
     * showDetail 是否显示详情(0:不显示,1显示) 默认0 是否显示详情(0:不显示,1显示)
     */
    
    public function GetPriceCalendar($policyId, $startDate, $endDate = "", $showDetail = ""){
        $parm = '<policyId>'.$policyId.'</policyId><startDate>'.$startDate.'</startDate><endDate>'.$endDate.'</endDate><showDetail>'.$showDetail.'</showDetail>';
        $xml_data = $this->get_xml_data("GetPriceCalendar", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 提交订单接口
     * 提交预订信息
     * sceneryId 景区ID
     * bMan 预订人
     * bMobile 预订人手机
     * bAddress	预订人地址
     * bPostCode 预订人邮编
     * bEmail 预订人邮箱
     * tName 取票人姓名
     * tMobile 取票人手机
     * policyId	政策ID
     * tickets 预订票数
     * travelDate 旅游日期 格式:2012-07-19
     * orderIP 预订人IP
     * idCard 二代身份证 根据policyId系统进行判断电景点是否要否输入二代身份证，如需身份证的则必传。
     * guest 其他游玩人信息 这一点需要注意,如果tickets=2,那么此节点下必须有两个gName节点,其一就是取票人
     * gName 游玩人姓名 从guest数组中传入的值，如果此景区需要实名制此节点必须输入
     * gMobile 游玩人手机	从guest数组中传入的值，如果此景区需要实名制此节点必须输入
     */
    
    public function SubmitSceneryOrder($sceneryId, $bMan, $bMobile, $tName, $tMobile, $policyId, $tickets, $travelDate, $orderIP, $guest = array(), $bAddress = "", $bPostCode = "", $bEmail = "", $idCard = ""){
        $parm = '<sceneryId>'.$sceneryId.'</sceneryId>';
        $parm .= '<bMan>'.$bMan.'</bMan>';
        $parm .= '<bMobile>'.$bMobile.'</bMobile>';
        $parm .= '<bAddress>'.$bAddress.'</bAddress>';
        $parm .= '<bPostCode>'.$bPostCode.'</bPostCode>';
        $parm .= '<bEmail>'.$bEmail.'</bEmail>';
        $parm .= '<tName>'.$tName.'</tName>';
        $parm .= '<tMobile>'.$tMobile.'</tMobile>';
        $parm .= '<policyId>'.$policyId.'</policyId>';
        $parm .= '<tickets>'.$tickets.'</tickets>';
        $parm .= '<travelDate>'.$travelDate.'</travelDate>';
        $parm .= '<orderIP>'.$orderIP.'</orderIP>';
        $parm .= '<idCard>'.$idCard.'</idCard>';
        if(!empty($guest) && is_array($guest)){
            $parm .= '<otherGuest>';
            foreach($guest as $v){
                $parm .= '<guest>';
                $parm .= '<gName>'.$v["gName"]."</gName>";
                $parm .= '<gMobile>'.$v["gMobile"].'</gMobile>';
                $parm .= '</guest>';
            }
            $parm .= '</otherGuest>';
        }
        $xml_data = $this->get_xml_data("SubmitSceneryOrder", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 景区取消订单
     * 取消景区订单（如果需要重新修改订单，则采用先取消订单再重新下单方式）
     * serialId	订单流水号
     * cancelReason 取消原有 1行程变更; 2通过其他更优惠的渠道预订了景区; 3对服务不满意; 4其他原因; 5信息错误重新预订; 12景区不承认合作; 17天气原因; 18重复订单	
     */
    
    public function CancelSceneryOrder($serialId, $cancelReason){
        $parm = "<serialId>".$serialId."</serialId><cancelReason>".$cancelReason."</cancelReason>";
        $xml_data = $this->get_xml_data("CancelSceneryOrder", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 景区同步单列表
     * 获取联盟订单列表
     * page 当前页 默认1
     * pageSize	每页条数 默认10条 最大100条
     * cStartDate 创建单开始时间	
     * cEndDate	创建单束时间
     * tStartDate 旅游开始时间
     * tEndDate 旅游结束时间	
     * orderStatus 订单状态 0:所有订单; 1:新建（待游玩）订单; 2:取消订单; 3:游玩过订单; 4:预订未游玩订单
     * serialId 订单流水号
     * bookingMan 预订人
     * bookingMobile 预订人电话
     * guestName 游玩人
     * guestMobile 游玩人电话	
     */
    
    public function GetSceneryOrderList($option = array()){
        $page = "";
        $pageSize = "";
        $cStartDate = "";
        $cEndDate = "";
        $tStartDate = "";
        $tEndDate = "";
        $orderStatus = "";
        $serialId = "";
        $bookingMan = "";
        $bookingMobile = "";
        $guestName = "";
        $guestMobile = "";
        foreach($option as $k => $v){
            switch($k){
                case "page":
                    $page = $v;
                    break;
                case "pageSize":
                    $pageSize = $v;
                    break;
                case "cStartDate":
                    $cStartDate = $v;
                    break;
                case "cEndDate":
                    $cEndDate = $v;
                    break;
                case "tStartDate":
                    $tStartDate = $v;
                    break;
                case "tEndDate":
                    $tEndDate = $v;
                    break;
                case "orderStatus":
                    $orderStatus = $v;
                    break;
                case "serialId":
                    $serialId = $v;
                    break;
                case "bookingMan":
                    $bookingMan = $v;
                    break;
                case "bookingMobile":
                    $bookingMobile = $v;
                    break;
                case "guestName":
                    $guestName = $v;
                    break;
                case "guestMobile":
                    $guestMobile = $v;
                    break;
            }
        }
        $parm = '<page>'.$page.'</page>
                <pageSize>'.$pageSize.'</pageSize>
                <cStartDate>'.$cStartDate.'</cStartDate>
                <cEndDate>'.$cEndDate.'</cEndDate>
                <tStartDate>'.$tStartDate.'</tStartDate>
                <tEndDate>'.$tEndDate.'</tEndDate>
                <orderStatus>'.$orderStatus.'</orderStatus>
                <serialId>'.$serialId.'</serialId>
                <bookingMan>'.$bookingMan.'</bookingMan>
                <bookingMobile>'.$bookingMobile.'</bookingMobile>
                <guestName>'.$guestName.'</guestName>
                <guestMobile>'.$guestMobile.'</guestMobile>';
        $xml_data = $this->get_xml_data("GetSceneryOrderList", $parm);
        return $this->get_response($xml_data); 
    }
    
    /*
     * 景区同步单列表
     * 获取景区订单详情
     * writeDB 数据源库 0读库 1写库 默认为0
     * serialIds 订单流水号 逗号分隔，最多20个
     */
    public function GetSceneryOrderDetail($serialIds, $writeDB = ""){
        $parm = '<writeDB>'.$writeDB.'</writeDB><serialIds>'.$serialIds.'</serialIds>';
        $xml_data = $this->get_xml_data("GetSceneryOrderDetail", $parm);
        return $this->get_response($xml_data); 
    }
}
