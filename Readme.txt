
This is a basic demonstrator of the Amazon AWS Rekogintion service. It was created as part of an article in the Swedish Computer Magazin Datormagazin. http://www.datormagazin.se




Install Composer and PHP.
Create an account on Amazon AWS
Copy the credentials template /config/credentials_default.ini to /config/credentials.ini
Create credentials (use the My Credentials menu hen logged in to your AWS account). Copy access key and aws secret key to the corresponding fileds in the credentials.ini file. Keep this file secret. Don't share this file and don't check it in to Github or other source control services.


Install Amazon AWS CLI (command line interface)
http://docs.aws.amazon.com/cli/latest/userguide/installing.html
On Ubuntu You can try
sudo apt-get install awscli
