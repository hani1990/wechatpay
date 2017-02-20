# wechatpay
微信支付
下单处理

/*
 *支付下单
*/

public function actionIndex()

    {
        //商品id
        $good_id = Yii::$app->request->get('goodid');
        //①、获取用户openid
        $tools = new JsApiPay();
        $openId = $tools->GetOpenid();
        //商品描述
        $goodBody = "xxx";
        //商品金额(分)
        $amount = Goods::get_price($good_id) ;
        //商户创建订单号
        $orderid = Recharge::create_orderid();
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        //商品或支付单简要描述
        $input->SetBody($goodBody);
        $input->SetAttach("xxx");
        //商户系统内部的订单号
        $input->SetOut_trade_no($orderid);
        //设置订单总金额 分
        $input->SetTotal_fee($amount * 100 );
        //交易起始时间
        $input->SetTime_start(date("YmdHis"));
        //交易结束时间
        $input->SetTime_expire(date("YmdHis", time() + 600));
        //商品标记
        $input->SetGoods_tag("memeliao");
        $input->SetNotify_url(Yii::$app->params['wechat']['wxpay_notify_url']);
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order = WxPayApi::unifiedOrder($input);
        $jsApiParameters = $tools->GetJsApiParameters($order);
        //订单保存
        Recharge::create_order($openId, $amount,$orderid, $good_id);
        return $this->render('index',[
            'jsApiParameters'=>$jsApiParameters,
            'out_trade_no' => $orderid,
            'price' => $amount,
        ]);
    }
    
    
    
 支付页面
    
<script type="text/javascript">
    //调用微信JS api 支付
    function jsApiCall()
    {
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest',
            <?php echo $jsApiParameters; ?>,
            function(res){
                WeixinJSBridge.log(res.err_msg);
                // alert(res.err_msg);
                window.location.href = "<?php echo Url::toRoute(['payment/query'])?>"+"?out_trade_no="+"<?php echo $out_trade_no;?>";
                //alert(res.err_code+res.err_desc+res.err_msg);
            }
        );
    }

    function callpay()
    {
        if (typeof WeixinJSBridge == "undefined"){
            if( document.addEventListener ){
                document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
            }else if (document.attachEvent){
                document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
            }
        }else{
            jsApiCall();
        }
    }
</script>



<br/>

<div class="weui_msg">
    <div class="weui_icon_area"><i class="weui_icon_msg weui_icon_info"></i></div>
    <div class="weui_text_area">
        <h2 class="weui_msg_title">该笔订单支付金额为<span><?php echo $price;?>元</span></h2>

    </div>
    <div class="weui_opr_area">
        <p class="weui_btn_area">
            <a href="javascript:;" class="weui_btn weui_btn_warn" onclick="callpay()">立即支付</a>
        </p>
    </div>
</div>


 /*
     * 支付回调处理
     * 支付成功才会走到回到处理
     * */

    public function actionNotify()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        // 验签
        try {
            WxPayResults::Init($postStr);
        } catch (WxPayException $e){
            $msg = $e->errorMessage();
            return false;
        }

        //订单回调处理
        $order_id = $postObj->out_trade_no;
        //根据自己的业务处理订单操作
        $ret = Recharge::handle_order($order_id);
        if($ret){
            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else {
            //订单状态已更新，直接返回
            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
    }
    
    
    
    /*
     * 支付页面跳转到 查询页面
     * */
    public function actionQuery()
    {
        $orderid = Yii::$app->request->get('out_trade_no');
        $order = Recharge::get_order($orderid);
        $pay_status = 0;
        if($order['status'] == 1){
            $pay_status = 1;
            $msg = '支付成功';
        }else{
            $input = new WxPayOrderQuery();
            $input->SetOut_trade_no($orderid);
            $ret = WxPayApi::orderQuery($input);
            if( isset($ret['result_code']) &&  $ret["trade_state"] == 'SUCCESS' ){
                Recharge::handle_order($orderid);
                $pay_status = 1;
                $msg = "支付成功";
            }else{
                $pay_status = 0;
                $msg = isset($ret['err_code_des']) ? $ret['err_code_des'] : $ret["trade_state_desc"];
                Recharge::update_order($orderid, -1, $msg);
            }
        }
        return $this->render('query',[
            'pay_status' => $pay_status,
            'msg'=>$msg,
        ]);
    }
