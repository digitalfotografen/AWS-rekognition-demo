<?php
namespace AwsTest;

use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;

class S3Synchronizer {
    
    protected $s3; // s3 instance

    protected $bucket;
    
    protected $imageFolder;
    
    function __construct($AwsSdk, $bucket, $imageFolder)
    {
        $this->s3 = $AwsSdk->createS3();
        $this->s3->registerStreamWrapper();
        $this->bucket = $bucket;
        $this->imageFolder = $imageFolder;
    }

    public function listKeys()
    {
        $list = [];
        try {
            $result = $this->s3->listObjects(['Bucket' => $this->bucket]);
            foreach ($result['Contents'] as $object) {
                $list[$object['Key']] = $object['LastModified'];
            }
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }
        return $list;
    }

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

    public function synchronize()
    {
        $imageKeys = $this->listKeys();
        $fileList = $this->listFiles();

        $uploads = array_diff_key($fileList, $imageKeys);
        foreach ($uploads as $key => $file){
            $this->upload($key, $file);
        }

        $deletes = array_diff_key($imageKeys, $fileList);
        foreach ($deletes as $key => $date){
            $this->delete($key);
        }
    }

    protected function upload($key, $file)
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

    protected function delete($key)
    {
        $response = $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        if ($response['@metadata']['statusCode'] == '204'){
            echo sprintf("Deleted %s from bucket \n", $key);
        } else {
            print_r($response);
        }
    }
/*


echo "Image files\n";
$fileList = listFiles($imagesPath);
echo print_r($fileList, true);
echo "\n\n";

foreach($fileList as $key => $value){
    if (!in_array($key, $bucketKeys)){
        echo "uploading file:" . $value;
        $uploader = new MultipartUploader($s3, $value, [
            'bucket' => $imagesBucket,
            'key'    => $key,
        ]);

        try {
            $result = $uploader->upload();
            echo "Upload complete: {$result['ObjectURL']}\n";
        } catch (MultipartUploadException $e) {
            echo $e->getMessage() . "\n";
        }
    }
}

echo "\n";

foreach ($fileList as $key => $value){
    
    $labels = $rekognition->detectLabels([
        'Image' => [
            'S3Object' => [
                'Bucket' => $imagesBucket,
                'Name' => $key,
            ],
        ],
    ]);

    echo print_r($labels['Labels'], true);
}



*/
    public function filename2key($filename)
    {
        $table = [
            'å' => 'aa',
            'ä' => 'ae',
            'ö' => 'oe',
            ',' => '',
        ];
    
        return strtr( strtolower( trim($filename) ), $table);
    
    }


    protected function isImageFile($path, $file){
        $fullPath = $path . '/' . $file;
        return is_readable($fullPath) && (mime_content_type($fullPath ) == "image/jpeg");
    }
}