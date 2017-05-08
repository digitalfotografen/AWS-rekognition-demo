<?php
namespace AwsTest;
require 'vendor/autoload.php';

use Aws\Credentials\CredentialProvider;
use Aws\Rekognition\RekognitionClient;
use Aws\Sdk;
use AwsTest\RekognitionWrapper;
use AwsTest\S3Synchronizer;

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
$shortopts .= 'm'; // Moderate Images, Search for explicit content
$shortopts .= 'v'; // verbose on

$longopts  = [
    'clear' => 'Clear bucket and collection -c',
    'faces' => 'Detect faces in all images -f',
    'help' => 'Help -h',
    'labels' => 'Detect labels in all images -l',
    'moderate' => 'Moderate Images, Search for explicit content -m',
    'verbose' => 'Verbose output -v',
];

$options = getopt($shortopts, $longopts);

if (isset($options['h']) || isset($options['help'])){
    help();
} else {
    if (is_readable("config/config.php")){
        require 'config/config.php';
        initialize($config, $options);
    } else {
        echo "Error: Missing bucket configuration. Please copy the default_config.php to config.php and enter required values\n";
        exit(1);
    }

    $newImages = $s3Synchronizer->synchronize();

    echo "=== keys and files ===\n";
    print_r($s3Synchronizer->listFiles());

    foreach ($newImages as $key){    
        // detect labels
        $rekognition->detectLabels($key);

        // detect labels
        $rekognition->detectModerationLabels($key);
    
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

if (isset($options['m']) || isset($options['moderate'])){
    detectModerationLabels();
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
        'region'  => $config['region'],
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
        $rekognition->indexFaces($key);
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

function detectModerationLabels(){
    global $rekognition, $s3Synchronizer;
    $files = $s3Synchronizer->listFiles();
    foreach($files as $key => $value){
        $rekognition->detectModerationLabels($key);
    }
}