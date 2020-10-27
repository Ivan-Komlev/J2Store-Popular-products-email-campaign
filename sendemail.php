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

function cron_sendemail()
{

	echo '
Sending email to customers:
';

	$message_text=getEmailText();
	if($message_text=='')
	{
		echo '
Email text not found: please check Custom Tables/Tables/Promotion Emails (Description)
';
		return false;
	}
	
		
	$users=getUsersWithEmailNotSent(1);
	foreach($users as $user)
	{
		$email=$user['email'];
	
		$message_text=str_replace('{Name}',$user['client_name'],$message_text);
		
		$last_orders=getLastOrdersByUsreID($user['id']);
		
		if(count($last_orders)>0)
		{
			$order=$last_orders[0];
			
			$message_text=str_replace('{Product Name}',$order['orderitem_name'],$message_text);
			
			$product_details=getProductContent($order['product_id']);
			if($product_details!==null)
			{
				$message_text=str_replace('{LastPurchasedProduct}',renderProductDetails($product_details),$message_text);
				
				$ignore_product=$order['product_id'];
				
				$texts=array();
				$PopularProducts=getMostPopularProducts(2,$ignore_product);

				foreach($PopularProducts as $order)
				{
					$product_details=getProductContent($order['product_id']);
					if($product_details!==null)
						$texts[]=renderProductDetails($product_details);
					
				}
				
				$message_text=str_replace('{Products}',implode('',$texts),$message_text);
			}

			echo $message_text;
		}
	}
}
function renderProductDetails($product_details)
{
	if($product_details==null)
		return '';
	
	$link=WEBSITE_PATH.'es/'.$product_details['alias'];
	$product_text='<a href="'.$link.'" target="_blank"><div style="position:relative;width:300px;height:230px;overflow:hidden;display:inline-block;margin:15px;border:3px solid #00a3b3;border-radius:10px;padding:15px;text-align:center;">'
		
		.'<img src="'.WEBSITE_PATH.$product_details['image'].'" style="height:200px;" />'
		.'<div style="position:absolute;width:100%;bottom:0;left:0;text-align:center;"><h3 style="color:#00a3b3;">'.$product_details['title'].'</h3></div>'
		.'</div></a>';
				
	
	return $product_text;
}

function getEmailText()
{
	$db = JFactory::getDBO();

	$query = 'SELECT description FROM #__customtables_tables WHERE tablename='.$db->quote('promotionemails').' LIMIT 1';

	$db->setQuery($query);
	$recs=$db->loadAssocList();
	if(count($recs)==0)
		return '';

	return $recs[0]['description'];
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
	$wherearr[]='(SELECT id FROM #__customtables_table_promotionemails AS p WHERE p.es_user=u.id LIMIT 1) IS NULL';
	
	$selects=array();
	$selects[]='u.id AS id';
	$selects[]='u.name AS client_name';
	$selects[]='u.email AS email';
	$query = 'SELECT '.implode(',', $selects).' FROM #__users AS u WHERE '.implode(" AND ",$wherearr).' ORDER BY id DESC LIMIT '.$limit;
	
	$db->setQuery($query);

	return $db->loadAssocList();
}

function getLastOrdersByUsreID($userid)
{
	$db = JFactory::getDBO();
	$wherearr=array();
	
	$wherearr[]='o.user_id='.(int)$userid;
	
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




function sendEmail($email,$subject,$body,$files)
{
	$mainframe = JFactory::getApplication('site');
	$MailFrom 	= $mainframe->getCfg('mailfrom');
	$FromName 	= $mainframe->getCfg('fromname');
		
	$email='ivankomlev@gmail.com';

	$mail = JFactory::getMailer();

	
	$mail->IsHTML(true);
	$mail->addRecipient($email);
	$mail->setSender( array($MailFrom,$FromName) );
	$mail->setSubject($subject);
	

	$mail->setBody($bidy);

	$sent = $mail->Send();
}