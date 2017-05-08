<?php
namespace AwsTest;

use Aws\Exception\AwsException;
use Aws\Exception\MultipartUploadException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;

/**
 * Synchronizes a folder of files with an S3 bucket
 *
 */
class S3Synchronizer {
    
    public $bucket;
    protected $s3; // s3 instance    
    protected $imageFolder;
    
    /**
     * S3Synchronizer constructor
     * @param Aws\Sdk $AwsSdk
     * @param string $bucket bucket name
     * @param string $imageFolder path to image folder
     */
    function __construct($AwsSdk, $bucket, $imageFolder)
    {
        $this->s3 = $AwsSdk->createS3();
        $this->s3->registerStreamWrapper();
        $this->bucket = $bucket;
        $this->imageFolder = $imageFolder;
    }

    /**
     * list object keys from bucket
     * @return array key = object key, value = last modified
     */
    public function listKeys()
    {
        $list = [];
        try {
            $result = $this->s3->listObjects(['Bucket' => $this->bucket]);
            $contents = isset($result['Contents']) ? $result['Contents'] : [];
            foreach ($contents as $object) {
                $key = $object['Key'];
                $list[$key] = [
                    'Bucket' => $this->bucket, 
                    'Name' => $key,
                    'Modified' => $object['LastModified']
                ];
            }
        } catch (S3Exception $e) {
            echo "Oups, this failed. Have You configured Security Crentials and S3 Bucket correctly?\n\n";
            echo $e->getMessage() . "\n";

            exit(1);
        }

        return $list;
    }

    /**
     * list files in image folder
     * @return array key = object key, value = file path
     */
    public function listFiles(){
        $fileList = [];
        $d = dir($this->imageFolder);
        while (false !== ($entry = $d->read())) {
            if ($this->isImageFile($this->imageFolder, $entry)){
                $fileList[$this->filename2key($entry)] = $this->imageFolder . '/' . $entry;
            }
        }
        $d->close();
        return $fileList;
    }

    /**
     * synchronize image folder with bucket
     * @return array list of new images
     */
    public function synchronize()
    {
        $imageKeys = $this->listKeys();
        $fileList = $this->listFiles();
        $newImages = [];

        if (empty($fileList)){
            echo "There are no test images. Copy som image files to the images folder.\n";
        }
        
        $deletes = array_diff_key($imageKeys, $fileList);
        foreach ($deletes as $key => $date){
            $this->delete($key);
        }

        $uploads = array_diff_key($fileList, $imageKeys);
        foreach ($uploads as $key => $file){
            $this->upload($key, $file);
            $newImages[] = $key;
        }

        return $newImages;
    }

    /**
     * upload file to bucket
     * @param string $key object key
     * @param string $file path to image file
     * @return bool true on success
     */
    public function upload($key, $file)
    {
        $uploader = new MultipartUploader($this->s3, $file, [
            'bucket' => $this->bucket,
            'key'    => $key,
        ]);

        try {
            echo sprintf("Uploading image %s as %s \n", $file, $key);
            $result = $uploader->upload();
            echo "Upload complete: {$result['ObjectURL']}\n";
            return true;
        } catch (MultipartUploadException $e) {
            echo $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * delete object from bucket
     * @param string $key object key
     * @return bool true on success
     */
    public function delete($key)
    {
        $response = $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        try {
            if ($response['@metadata']['statusCode'] == '204'){
                echo sprintf("Deleted %s from bucket \n", $key);

                return true;
            } else {
                print_r($response);

                return false;
            }
        } catch (AwsException $e) {
            echo $e->getMessage() . "\n";

            return false;
        }
    }


    /**
     * delete all objects from bucket
     * @return bool true on success
     */
    public function clear()
    {
        $result = true;
        $keys = $this->listKeys();
        foreach($keys as $key => $value){
            $result &= $this->delete($key);
        }

        return $result;
    }

    /**
     * create object key from filename
     * convert common illegal characters to allowed characters
     * @param string $filename filename
     * @return string object key
     */
    protected function filename2key($filename)
    {
        $table = [
            'å' => 'aa',
            'ä' => 'ae',
            'ö' => 'oe',
            ',' => '',
        ];

        return strtr( strtolower( trim($filename) ), $table);
    }

    /**
     * check if file is a jpeg image file
     * @param string $path path
     * @param string $file filename
     * @return bool true if readlble and mime type is image/jpeg
     */
    protected function isImageFile($path, $file){
        $fullPath = $path . '/' . $file;
        return is_readable($fullPath) && (mime_content_type($fullPath ) == "image/jpeg");
    }
}