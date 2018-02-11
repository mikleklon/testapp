<?php

require_once("../conf/bootstrap.php");

//читаем данные и HTTP-запроса, строим из них XML по схеме
$hreq = new HTTP_Request2Xml("schemas/TestApp/DocumentListRequest.xsd");
$req=new TestApp_DocumentListRequest();
if (!$hreq->isEmpty()) {
	$hreq->validate();
	$req->fromXmlStr($hreq->getAsXML());
}

// формируем xml-ответ
$xw = new XMLWriter();
$xw->openMemory();
$xw->setIndent(TRUE);
$xw->startDocument("1.0", "UTF-8");
$xw->writePi("xml-stylesheet", "type=\"text/xsl\" href=\"stylesheets/TestApp/DocumentList.xsl\"");
$xw->startElementNS(NULL, "DocumentListResponse", "urn:ru:ilb:meta:TestApp:DocumentListResponse");
$req->toXmlWriter($xw);
// Если есть входные данные, проведем вычисления и выдадим ответ
if (!$hreq->isEmpty()) {
	//$pdo=new PDO("mysql:host=127.0.0.1;dbname=testapp;charset=utf-8","root","!23Qweasd",array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	$pdo=new PDO(
	    "mysql:host=localhost;dbname=testapp",
        "root",
        "!23Qweasd",
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            'charset' => 'utf8',
        )
    );
	//prior to PHP 5.3.6, the charset option was ignored. If you're running an older version of PHP, you must do it like this:
	$pdo->exec("set names utf8");
	$query = "SELECT * FROM document WHERE docDate BETWEEN :dateStart AND :dateEnd ";
    $ar = array(
        ":dateStart"=>$req->dateStart,
        ":dateEnd"=>$req->dateEnd,
    );
	if($req->name != ""){
	    $query .= " and displayName like (:name)";
	    $ar[":name"] = "%".$req->name."%";
    }
	$sth=$pdo->prepare($query);
	$sth->execute($ar);
	while($row=$sth->fetch(PDO::FETCH_ASSOC)) {
		$doc = new TestApp_Document();
		$doc->fromArray($row);
		$doc->toXmlWriter($xw);
	}
}
$xw->endElement();
$xw->endDocument();
if($req->outputFormat =="html"){
    //Вывод ответа клиенту
    header("Content-Type: text/xsl");
    echo $xw->flush();
}elseif($req->outputFormat =="pdf"){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $url = "http://tomcat-bystrobank.rhcloud.com/fopservlet/fopservlet";
    curl_setopt($ch, CURLOPT_URL, $url);
    //specify mime-type of source data
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/xml"));
    //post contents - fo souce
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xw->flush());
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //check http response code
    if ($code != 200) {
        throw new Exception($res . PHP_EOL . $url . " " . curl_error($ch), 450);
    }
    $attachmentName = "documentList.pdf";
    $headers = array(
        "Content-Type: application/pdf",
        "Content-Disposition: inline; filename*=UTF-8''" . $attachmentName
    );
    foreach ($headers as $h) {
        header($h);
    }
    echo $res;
}
