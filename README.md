# Cliq report mailer

This repo contains PHP scripts to send emails containing reports using checkin and checkout records from Zoho Cliq applications.

## Features

- Send weekly an individual email to each user containing hours worked for the last week
- Send every tow weeks an email with a XLXS report containing total hours worked per user durin the last two weeks.

## Setup

To run this application you need PHP 8.1 or higher, and Composer installed.

Read for more info about how to install Composer: https://getcomposer.org/doc/00-intro.md

Clone the repo and install dependencies.

```bash
git clone https://github.com/appox-ai/cliq_report_mailer.git
cd cliq_report_mailer
composer install
```

Create a config.php at the root of the project to set mail credentials.

```php
<?php
$conf=[
	'mailHost' => 'url_mailHost',
	'mailUsername' => 'user@domain-example.com',
	'mailPassword' => 'mail-password',
];
global $conf;
?>
```

This application get users listed in users.json file to compute reports and send emails. Edit this file to add or remove users assuring that contain only active users. The *name* parameter must match to the *name* value in the CSV file that contains the checkin and checkout records.

```json
[
	{
		"name":"Jorge",               // parameter used in user_report.php and is required to match with the name in the CSV file
		"mail":"jorge@mail.com"       // parameter used in user_report.php and is required
	},
	{
		"name":"Lola",
		"mail":"lola@mail.com",
		"id": 1,                      // parameter used in weekly_report.php and is optional
		"hours_per_day": 7            // parameter used in weekly_report.php and is optional
	},
	{	
		"name": "Santiago Brochero",  
		"mail": "sbrochero2@appox.ai",
		"agreements": [               // parameter used in monthly_report.php and is optional
			{
				"contract": "Ciao-RAMQ-20220438",
				"hours_per_day": 7,
				"start_date": "2025-06-23",
				"end_date": "2026-08-06"
			}
		]
	}
]
```
**NOTE**: user with **id** property will be used to create the report every two weeks. User without **id** property will be omitted.

## Execution

Configure the cron task to run weekly the following scripts:

- **weekly_report.php**: Run every week at 8:30 on Monday
- **two_weeks_report.php**: Run every week at 13:00 on Monday.

Is important to update the input/data.csv file before the CRON task run the scripts.

input/data.csv example
~~~
"866012951","Jorge","2025-02-26","8:32","16:2","7.5" 
"866012951","Jorge","2025-02-27","8:34","16:2","7.5" 
"860955724","Lola","2025-02-24","9:32","9:26","23.9" 
"860955724","Lola","2025-02-27","9:3","16:59","7.9" 
~~~

The input/data.csv file must be update weekle to send the right info to users before the CRON task run the scripts.
