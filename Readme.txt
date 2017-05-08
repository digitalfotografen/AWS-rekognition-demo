
This is a basic demonstrator of the Amazon AWS Rekogintion service. It was created by Ulrik Södergren for an article in the Swedish Computer Magazin -  Datormagazin. http://www.datormagazin.se

## How to install and test drive:
The easiest way to install and test this application is with Composer. https://getcomposer.org/

Use or terminal and go to the path were You like to install end test the application. Then write:

    composer create-project digitalfotografen/aws-rekognition-demo rekognition

This will create the required folder structure, download source code from GitHub and install required libraries.

Create an account on Amazon AWS. Then create AMI Credentials and an S3 Bucket

Copy the credentials template /config/credentials_default.ini to /config/credentials.ini
Create credentials (use the My Credentials menu hen logged in to your AWS account). Copy access key and aws secret key to the corresponding fileds in the credentials.ini file. Keep this file secret. Don't share this file and don't check it in to Github or other source control services.

Copy the configuration file config/config_default.php to config/config.php . Edit the configuration. Enter name of Your S3 Bucket and AWS region.

Copy a few image files to the Images folder. Then run the application.

This is basic command line application. To run it use
php src/AwsTest.php

To get more output use the -v flag for verbose
To get help use the -h flag

Have fun!
Ulrik