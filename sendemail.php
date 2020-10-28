<?php
/**
* Promotion email sender
* @author    Ivan Komlev
* @copyright Copyright (C) 2020 Ivan Komlev. All rights reserved.
* @license	 GNU/GPL
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

define('WEBSITE_PATH','https://cda.com.pa/shop/');
define('IMAGE_WIDTH',565);
define('FONT_SIZE',22);


function cron_sendemail()
{
	echo '
Sending email to customers:<br/>
';
	$table=getCustomTable();
	
	if($table==null)
	{
		echo '
Email text not found: please check Custom Tables/Tables/Promotion Emails (Description)<br/>
';
		return false;
	}
	
	
	$subject=$table['tabletitle'];
	$message_text=$table['description'];
	
	$users=getUsersWithEmailNotSent(1);
	foreach($users as $user)
	{
		$email=$user['email'];
		
		$client_name=trim($user['client_name']);
		$client_name=mb_convert_case($client_name, MB_CASE_TITLE, "UTF-8");
		
		$message_text=str_replace('{Name}',$client_name,$message_text);
		
		$last_orders=getLastOrdersByEmail($email);

		if(count($last_orders)>0)
		{
			$order=$last_orders[0];
			
			
			$message_text=str_replace('{Product Name}',$order['orderitem_name'],$message_text);
			
			$product_details=getProductContent($order['product_id']);
			if($product_details!==null)
			{
				$message_text=str_replace('{LastPurchasedProduct}',renderProductDetails($product_details,IMAGE_WIDTH),$message_text);
				
				$ignore_product=$order['product_id'];
				
				$texts=array();
				$PopularProducts=getMostPopularProducts(2,$ignore_product);

				foreach($PopularProducts as $order)
				{
					$product_details=getProductContent($order['product_id']);
					if($product_details!==null)
						$texts[]=renderProductDetails($product_details,(floor(IMAGE_WIDTH/2)-30-3),'display:inline-block;');
					
				}
				
				$message_text=str_replace('{Products}',implode('',$texts),$message_text);
			}

			//echo $message_text;
			sendEmail($email,$subject,$message_text);
		}
		saveEmailSentLog($user['id'],$email,$client_name);
	}
}
function renderProductDetails($product_details,$size,$style='')
{
	if($product_details==null)
		return '';
	
	$link=WEBSITE_PATH.'es/'.$product_details['alias'];
	$product_text='<a href="'.$link.'" target="_blank"><div style="'.$style.';background-color:white;position:relative;width:'.$size.'px;height:'.($size-70).'px;overflow:hidden;margin:15px;border:3px solid #00a3b3;border-radius:10px;padding:15px;text-align:center;">'
		
		.'<img src="'.WEBSITE_PATH.$product_details['image'].'" style="height:'.($size-100).'px;" />'
		.'<div style="position:absolute;width:100%;bottom:0;left:0;text-align:center;"><h3 style="color:#00a3b3;font-seze:'.FONT_SIZE.'px;">'.$product_details['title'].'</h3></div>'
		.'</div></a>';
				
	
	return $product_text;
}

function getCustomTable()
{
	$db = JFactory::getDBO();

	$query = 'SELECT tabletitle,description FROM #__customtables_tables WHERE tablename='.$db->quote('promotionemails').' LIMIT 1';

	$db->setQuery($query);
	$recs=$db->loadAssocList();
	if(count($recs)==0)
		return null;

	return $recs[0];
}

function getProductContent($product_id)
{
	$db = JFactory::getDBO();

	$selects=array();
	$selects[]='product_source_id';
	$selects[]='(SELECT title FROM #__content AS c WHERE c.id=product_source_id LIMIT 1) AS title';
	$selects[]='(SELECT alias FROM #__content AS c WHERE c.id=product_source_id LIMIT 1) AS alias';
	$selects[]='(SELECT CONCAT(c.introtext,c.fulltext) FROM #__content AS c WHERE c.id=product_source_id LIMIT 1) AS content';
	$selects[]='(SELECT main_image FROM #__j2store_productimages AS i WHERE i.product_id=j2store_product_id LIMIT 1) AS image';
	
	$query = 'SELECT '.implode(',', $selects).' FROM #__j2store_products WHERE j2store_product_id='.(int)$product_id.' AND product_source='.$db->quote('com_content').' LIMIT 1';

	$db->setQuery($query);
	$recs=$db->loadAssocList();
	
	if(count($recs)==0)
		return null;
	
	return $recs[0];
}


function getUsersWithEmailNotSent($limit)
{
	//Limit the list to users who didn't receive an in 1 month.
		
	$db = JFactory::getDBO();
	$wherearr=array();
	//$wherearr[]='(SELECT id FROM #__customtables_table_promotionemails AS p WHERE p.es_user=u.id LIMIT 1) IS NULL';
	$wherearr[]='(SELECT id FROM #__customtables_table_promotionemails AS p WHERE p.es_email=o.user_email LIMIT 1) IS NULL';
	
	$selects=array();
	
	$selects[]='o.user_id AS id';
	$selects[]='o.j2store_order_id AS order_id';
	$selects[]='(SELECT CONCAT(COALESCE(`billing_last_name`,"")," ",COALESCE(`billing_first_name`,"")," ",COALESCE(`billing_middle_name`,"")) FROM #__j2store_orderinfos AS oi WHERE oi.order_id=o.order_id LIMIT 1) AS client_name';
	$selects[]='o.user_email AS email';
	$query = 'SELECT '.implode(',', $selects).' FROM #__j2store_orders AS o WHERE '.implode(" AND ",$wherearr).' ORDER BY j2store_order_id DESC LIMIT '.$limit;
	
	
	$db->setQuery($query);

	return $db->loadAssocList();
}

function getLastOrdersByEmail($email)
{
	$db = JFactory::getDBO();
	$wherearr=array();
	
	$wherearr[]='o.user_email='.$db->quote($email);
	
	$selects=array();

	$selects[]='product_id';
	$selects[]='orderitem_name';
	
	$inner=' INNER JOIN #__j2store_orders AS o ON o.order_id=oi.order_id';

	$query = 'SELECT '.implode(',', $selects).' FROM #__j2store_orderitems AS oi '.$inner.' WHERE '.implode(" AND ",$wherearr);;
	$query.=' ORDER BY oi.created_on DESC LIMIT 1';//Last 1 order

	$db->setQuery($query);

	return $db->loadAssocList();
}



function getMostPopularProducts($how_many_products,$ignore_product)
{
	$db = JFactory::getDBO();
	$wherearr=array();
	
	$wherearr[]='product_id!='.(int)$ignore_product;
	$wherearr[]='orderitem_type="normal"';
	
	$selects=array();
	
	$selects[]='COUNT(product_id) AS popularity';
	$selects[]='orderitem_name';
	$selects[]='product_id';
	
	$query = 'SELECT '.implode(',', $selects).' FROM #__j2store_orderitems AS oi WHERE '.implode(" AND ",$wherearr);;
	$query.=' GROUP BY product_id ORDER BY COUNT(product_id) DESC LIMIT '.$how_many_products;

	$db->setQuery($query);

	return $db->loadAssocList();
}

function saveEmailSentLog($userid,$email,$client_name)
{
	echo 'Save log
';
	$db = JFactory::getDBO();
	
	$sets=array();
	$sets[]='es_user='.$userid;
	$sets[]='es_name='.$db->quote($client_name);
	$sets[]='es_email='.$db->quote($email);
	$sets[]='es_datetime=NOW()';
	
	$query='INSERT INTO #__customtables_table_promotionemails SET '.implode(', ',$sets);
	$db->setQuery( $query );
	$db->execute();


}


function sendEmail($email,$subject,$body)
{
	
	$config = JFactory::getConfig();
	
	$MailFrom 	= $config->get( 'mailfrom' );
	$FromName 	= $config->get( 'fromname' );
	
	//$email='markodearco@gmail.com';
	$email='ivankomlev@gmail.com';


		echo '
--Sending email to '.$email.'<br/>
';

	$mail = JFactory::getMailer();
	
	$mail->IsHTML(true);
	$mail->addRecipient($email);
	$mail->setSender( array($MailFrom,$FromName) );
	$mail->setSubject($subject);
	

	$mail->setBody($body);

	$sent = $mail->Send();
}