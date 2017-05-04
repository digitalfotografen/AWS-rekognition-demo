<?php
namespace AwsTest;
require 'vendor/autoload.php';
require 'config/config.php';

use Aws\Credentials\CredentialProvider;
use Aws\Rekognition\RekognitionClient;
use Aws\Sdk;
use AwsTest\RekognitionWrapper;
use AwsTest\S3Synchronizer;


$config = [
    'imagesPath' => 'images',
    'credentialsPath' => 'config/credentials.ini',
    'bucket' => 'ulrik.ekognition.test'
];

$rekognition;
$s3Synchronizer;


/**
 * Command line options
 */  
$shortopts = '';
$shortopts .= 'c'; // Clear bucket and collection
$shortopts .= 'f'; // Detect faces in all images
$shortopts .= 'h'; // help
$shortopts .= 'l'; // Detect labels in all images
$shortopts .= 'm'; // Search and match faces with face collection
$shortopts .= 'v'; // verbose on

$longopts  = [
    'clear' => 'Clear bucket and collection',
    'faces' => 'Detect faces in all images',
    'help' => 'Help',
    'labels' => 'Detect labels in all images',
    'match' => 'Search and match faces with face collection',
    'verbose' => 'Verbose output',
];

$options = getopt($shortopts, $longopts);


//$rekognition->listLabels();
//$rekognition->listFaces();

if (isset($options['h']) || isset($options['help'])){
    help();
} else {
    initialize($config, $options);
    $newImages = $s3Synchronizer->synchronize();

    echo "=== keys and files ===\n";
    print_r($s3Synchronizer->listFiles());

    foreach ($newImages as $key){    
        // detect labels
        $rekognition->detectLabels($key);
    
        // does image contain faces?
        if ($rekognition->detectFaces($key) > 0){
        
            // is this face known from previous image
            $matchingFaces = $rekognition->searchFaces($key);
            if (count($matchingFaces) == 0){
                // Add face to collection
                $rekognition->indexFaces($key);
            }
        }
    }
}

if (isset($options['l']) || isset($options['labels'])){
    detectLabels();
}

if (isset($options['f']) || isset($options['faces'])){
    detectFaces();
}

if (isset($options['m']) || isset($options['match'])){
    matchFaces();
}

if (isset($options['c']) || isset($options['clear'])){
    clear();
}


function initialize($config, $options) {
    global $rekognition, $s3Synchronizer;
    // Use credentials from the config/credentials.ini
    $provider = CredentialProvider::ini('default', $config['credentialsPath']);

    // Use the eu-west-1 region (Ireland) and latest version of each client.
    $sharedConfig = [
        'region'  => 'eu-west-1',
        'version' => 'latest',
        'credentials' => $provider,
    ];

    $AwsSdk = new Sdk($sharedConfig);
    $s3Synchronizer = new S3Synchronizer($AwsSdk, $config['bucket'], $config['imagesPath']);
    $rekognition = new RekognitionWrapper($AwsSdk, $s3Synchronizer);
    if (isset($options['v']) || isset($options['verbose'])){
        $rekognition->setVerbose(true);
    }
}

function clear(){
    global $config, $rekognition, $s3Synchronizer;
    $s3Synchronizer->clear();
    $rekognition->deleteCollection();
}

function help(){
    global $longopts;
    foreach($longopts as $key => $value){
        echo sprintf("%s : %s \n", $key, $value);
    }
}

function detectFaces(){
    global $rekognition, $s3Synchronizer;
    $files = $s3Synchronizer->listFiles();
    foreach($files as $key => $value){
        $rekognition->detectFaces($key);
    }
}

function matchFaces(){
    global $rekognition, $s3Synchronizer;
    $files = $s3Synchronizer->listFiles();
    foreach($files as $key => $value){
        $rekognition->searchFaces($key);
    }
}

function detectLabels(){
    global $rekognition, $s3Synchronizer;
    $files = $s3Synchronizer->listFiles();
    foreach($files as $key => $value){
        $rekognition->detectLabels($key);
    }
}