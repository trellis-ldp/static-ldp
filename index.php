<?php

require_once "vendor/autoload.php";

$LDP = "http://www.w3.org/ns/ldp#";

$valid_types = [
    "text/turtle" => "turtle",
    "application/ld+json" => "jsonld",
    "application/n-triples" => "ntriples"];

$format = "text/turtle";

foreach (getallheaders() as $key => $val) {
    if (strtolower($key) === "accept") {
        $type = explode(";", $val)[0];
        if (array_key_exists($type, $valid_types)) {
            $format = $type;
        }
    }
}

header("Link: <" . $LDP . "BasicContainer>; rel=\"type\"");
header("Content-Type: $format");

$namespaces = new EasyRdf_Namespace();
$namespaces->set("ldp", $LDP);

$graph = new EasyRdf_Graph();

$subject = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
$predicate = $LDP . "contains";

$docroot = dirname($_SERVER['SCRIPT_FILENAME']);
$path = $_SERVER['REQUEST_URI'];

foreach(scandir($docroot . "/" . $path) as $filename) {
    if (substr($filename, 0, 1) !== ".") {
        $graph->addResource($subject, $predicate, $subject . $filename);
    }
}

echo $graph->serialise($valid_types[$format]);
