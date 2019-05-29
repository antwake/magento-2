README FILE FOR Blockchain MAGENTO 2.x Plugin

--------------------------------------------------------------------------------------

                     Configuration Steps

--------------------------------------------------------------------------------------
1) Open Project Directory and go to app folder.
2) Click on the code folder.
3) Create a folder named local. --skip this step if it's already created.
4) Unzip package and paste it in code folder
5) Give the read and write permission to your package folder
6) Run command "php bin/magento setup:upgrade".
7) Give permission to 'generated' within your project root folder folder by running this command "chmod -R 777 generated/"
---------------------------------------------------------------------------------------

                    Plugin Setting Steps 

---------------------------------------------------------------------------------------

1) Go to admin panel.
2) Open Stores -> Configuration -> Sales.
3) Select Blockchain from Payment Methods.
4) Enter details CLIENT ID, CLIENT SECRET, LOCATION ID and click "Save Config".