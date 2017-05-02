<?
	$pluginData[digipay][type] = 'payment';
	$pluginData[digipay][name] = 'پرداخت انلاین';
	$pluginData[digipay][uniq] = 'nextpay';
	$pluginData[digipay][description] = 'سرويس پرداخت آنلاين nextpay';
	$pluginData[digipay][author][name] = 'nextpay';
	$pluginData[digipay][author][url] = 'https://www.nextpay.ir';
	$pluginData[digipay][author][email] = 'info@nextpay.ir';
	
	$pluginData[digipay][field][config][1][title] = 'کلید مجوزدهی (Api Key)';
	$pluginData[digipay][field][config][1][name] = 'merchantID';

	function gateway__nextpay($data)
	{
		global $config,$smarty,$db;
		$api_key = $data[merchantID];
		$amount = $data[amount]/10;//rial be toman
        $callback = $data[callback];
		$order_id= $data[invoice_id];

        $client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
        $result = $client->TokenGenerator(
            array(
                'api_key' 	=> $api_key,
                'order_id'	=> $order_id,
                'amount' 		=> $amount,
                'callback_uri' 	=> $callback
            )
        );

        $result = $result->TokenGeneratorResult;

        if(intval($result->code) == -1)
		{
		$go = "Location: https://api.nextpay.org/gateway/payment/". $result->trans_id;
		redirect_to($go);
		}
		else
		{
		//-- نمایش خطا
		$data[title] = 'خطای سیستم';
		$data[message] = '<font color="red">خطا در ارتباط با بانک</font> شماره خطا: '.$result->code.'<br /><a href="index.php" class="button">بازگشت</a>';
		throw new Exception($data[message] );		
		}
	}
	
	//-- تابع بررسی وضعیت پرداخت
	function callback__nextpay($data)
	{
		global $db,$post;
		

		$order_id = $_POST['trans_id'];
		$trans_id = $_POST['trans_id'];
		$api_key = $data['merchantID'];

		
		$sql = 'SELECT * FROM `payment` WHERE `payment_rand` = ? LIMIT 1;';
		$sql = $db->prepare($sql);
		$sql->execute(array (
            $order_id
		));
		
		$payment 	= $sql->fetch();
		
		if ($payment[payment_status] == 1)
		{
			$amount = $payment[payment_amount];
			///////////////////

            $client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));
            $result = $client->PaymentVerification(
                array(
                    'api_key' => $api_key,
                    'trans_id'  => $trans_id,
                    'amount'	 => $amount/10,
                    'order_id'	=> $order_id
                )
            );
            $result = $result->PaymentVerificationResult;

			$pay = false;
            if(intval($result->code) == 0){
				$pay = true;
			} else {
				$pay = false;
			}
			///////////////////
					
			if($pay)
			{
				//-- آماده کردن خروجی
				$output[status]		= 1;
				$output[res_num]	= $order_id;
				$output[ref_num]	= $trans_id;
				$output[payment_id]	= $payment[payment_id];
			}
			else
			{
				$output[status]	= 0;
				$output[message]= 'خطا در پرداخت';
			}
		}
		else
		{
			//-- سفارش قبلا پرداخت شده است.
			$output[status]	= 0;
			$output[message]= 'این سفارش قبلا پرداخت شده است.';
		}
		
		return $output;
	}