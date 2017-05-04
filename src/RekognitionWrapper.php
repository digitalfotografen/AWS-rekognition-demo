<?php
namespace AwsTest;

use Aws\Exception\AwsException;
use Aws\Rekognition\Exception\RekognitionException;
use Aws\S3\S3Client;

class RekognitionWrapper {

    protected $s3Synchronizer; // s3 instance
    protected $rekognition;
    protected $faceCollectionId = "my_friends";
    protected $verbose = false; // echo api response from AWA
    
    /**
     * S3Synchronizer constructor
     * @param Aws\Sdk $AwsSdk
     * @param Aws\S3\S3Client $s3instance
     */
    function __construct($AwsSdk, $s3instance)
    {
        $this->s3Synchronizer = $s3instance;
        $this->rekognition = $AwsSdk->createRekognition();
        $this->initFaceCollection();
    }

    /**
     * Activate/deactivate verbose output
     * @param bool $verbose verboser true/false
     * @return void
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * Check if face collection already exists
     * If not, it creates a collection of face signatures
     * @return void
     */
    public function initFaceCollection()
    {
        $response = $this->rekognition->listCollections();
        $this->echoVerbose($response);
        if ($response['@metadata']['statusCode'] == '200'){
            $collectionIds = $response['CollectionIds'];
            if (!in_array($this->faceCollectionId, $collectionIds)){
                echo sprintf("Creating face collection with id: %s \n", $this->faceCollectionId);
                $response = $this->rekognition->createCollection(['CollectionId' => $this->faceCollectionId]);
                $this->echoVerbose($response);
            }
        }
    }

    /**
     * Calls Rekognition detectLables to output keywords
     * Echos labels and confidence score
     * @param string $key key to imagefile in S3 bucket
     * @return array of labels and confidence score
     */
    public function detectLabels($key)
    {
        $response = $this->rekognition->detectLabels([
            'Image' => [
                'S3Object'	=> [
                    'Bucket' => $this->s3Synchronizer->bucket,
                    'Name' => $key,
                ],
            ],
        ]);
        echo sprintf("\n\n===== Labels from image Image %s =====\n", $key);
        $this->echoVerbose($response);

        foreach ($response['Labels'] as $label){
            echo sprintf("Name: %s  Confidence: %f \n", 
                $label['Name'], 
                $label['Confidence']
            );
        }
        
        return $response['Labels'];
    }

    /**
     * Calls Rekognition detectFaces to output face locations
     * Echos faces with boundingbox, landmarks and confidence score
     * @param string $key key to imagefile in S3 bucket
     * @return int number of detected faces in image
     */
    public function detectFaces($key)
    {
        echo sprintf("\n\n===== Detecting faces in Image %s =====\n", $key);
        $response = $this->rekognition->detectFaces([
            'Image' => [
                'S3Object'	=> [
                    'Bucket' => $this->s3Synchronizer->bucket,
                    'Name' => $key,
                ],
            ],
        ]);
        $this->echoVerbose($response);
        
        $faceCount = empty($response['FaceDetails']) ? 0 : count($response['FaceDetails']);
        
        if ($faceCount > 0){
            echo sprintf("Found %d faces, here are the FaceDetails\n", $faceCount);
            print_r($response['FaceDetails']);
        } else {
            echo sprintf("No faces detected\n");
        }
        return $faceCount;
    }

    /**
     * Calls Rekognition indexFaces to create face signature
     * and adds face signature to collection
     * Echos faces with boundingbox, landmarks and confidence score
     * @param string $key key to imagefile in S3 bucket
     * @return int number of detected faces in image
     */
    public function indexFaces($key)
    {
        echo sprintf("\n\nIndexing faces %s \n", $key);
        $response = $this->rekognition->indexFaces([
            'CollectionId' => $this->faceCollectionId,
            'DetectionAttributes' => ['ALL'],
            'ExternalImageId' => $key,
            'Image' => [
                'S3Object'	=> [
                    'Bucket' => $this->s3Synchronizer->bucket,
                    'Name' => $key,
                ],
            ],
        ]);
        $this->echoVerbose($response);    
    }

    /**
     * Calls Rekognition searchFacesByImage to search image for faces
     * that matches signtures in collection
     * Echos file keys with matching faces
     * @param string $key key to imagefile in S3 bucket
     * @return array keys of images with matching faces
     */
    public function searchFaces($key)
    {
        echo sprintf("\n\nSearching for matching faces in image %s \n", $key);
        $matchingKeys = [];

        try {
            $response = $this->rekognition->searchFacesByImage([
                'CollectionId' => $this->faceCollectionId,
                'Image' => [
                    'S3Object'	=> [
                        'Bucket' => $this->s3Synchronizer->bucket,
                        'Name' => $key,
                    ],
                ],
                'MaxFaces' => 5
            ]);
            $this->echoVerbose($response);

            if (!empty($response['FaceMatches'])){
                foreach($response['FaceMatches'] as $match){
                    $matchingImage = $match['Face']['ExternalImageId'];
                    if ($matchingImage != $key){
                        $matchingKeys[$matchingImage] = $match;
                        echo sprintf("Image %s has a face that matches image %s \n", $key, $matchingImage);
                    }
                }
            }
        } catch (RekognitionException $e) {
            if ($this->verbose) 
                echo $e->getMessage() . "\n";
        }

        if (count($matchingKeys) == 0){
            echo "No matching faces detected\n";
        }

        return $matchingKeys;
    }

    /**
     * Calls Rekognition deleteCollection to remove face collection
     * @return void
     */
    public function deleteCollection()
    {
        $response = $this->rekognition->deleteCollection(['CollectionId' => $this->faceCollectionId]);
        $this->echoVerbose($response);
    }

    /**
     * helper function to echo response arrays
     * @param array $response response to echo
     * @return void
     */
    private function echoVerbose($response)
    {
        if ($this->verbose){
            echo "\n\n";
            print_r($response);
            echo "\n\n";
        }
    }
}

