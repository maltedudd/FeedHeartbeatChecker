# FeedHeartbeatChecker

FeedHeartbeatChecker is a script, that checks if a Feed is there an updated in a configurable timeslot. 
If a Feed isn't updated in that intervall time you get an Alert in an Telegram-Chat.

## Installation
Copy files in a Folder on your server. The files do not have to be public accessible.
Make your changes in config.php 

Add a cronjob in your crontab

```bash
* * * * * php /homepages/uXXXX/feedHeartbeatChecker/getFeedAndCheckIfUpdated.php
```

## Usage
Feel free to change the script however you like, so that it fits to your feeds. 

## Contributing
Pull requests are welcome. For major changes, please open an issue first
to discuss what you would like to change.