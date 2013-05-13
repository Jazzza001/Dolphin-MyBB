#Dolphin To MyBB
Basic script to import Dolphin forum data to a MyBB forum.

##Imports
- all users with basic profile information
- all forums, threads and posts

##Requirements
- MyBB 1.6 installed
- Dolphin 7.1 installed (may be compatible with other versions)

##Installation
- Add your MyBB and Dolphin database connection details to the PHP script
- Upload all files to your server that has a Dolphin and MyBB installation
 
##Notes
- All users, forums, threads and posts in your MyBB installation will be deleted in favour of the Dolphin data
- When you run the script there will be **no** confirmation
- Your users will need to reset their passwords through MyBB to access their profiles
- HTML is stripped from all Dolphin posts