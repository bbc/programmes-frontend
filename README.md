/programmes
===========

/programmes version 3. Finally.

Static Assets
-------------

Then perform `npm install`

To perform the development tasks
`npm run gulp`

To perform the dist tasks (for ci)
`npm run gulp-ci`

To watch for file changes
`npm run watch`

Composer and cache:clear
------------------------

**The problem:** If you run composer install then will be executed a group of 
console commands. One of these is `bin/console cache:clear`. This command try 
to connect to Redis server in localhost, so composer consider that redis 
server is in the environment in which is executed, but is only inside the 
VM, giving errors. Neither Composer nor `cache:clear` accept options to 
handle this in a better way.
 
**Replication:** If you try to run the `clear:cache` console command (with `composer install`) 
from outside de VM you will have you an error as we cannot connect to redis inside 
the VM using composer from outside. 
  
**Solution:** run `composer install` inside the VM. 



Translations
------------

### Updating translation files with new placeholders

This is a two step process. You need to update both the programmes.pot template
and the English translation file. Sorry, but that's the way GetText expects you
to work.


#### To add an entry

Open `translations/programmes.pot` and add your new
entry.

```
#.The English translation
msgid "my_shiny_new_entry"
msgstr ""
```

The comment should contain the full english translation and is needed as
guidance for translators in other languages.

**msgid can only contain the characters [A-Za-z0-9_:-].** This is a limitation
of our translate-tool app and not the PO file format itself.

Entries in the template file **should be in alphabetical order**. This is so
that we can reduce the potential for merge noise, allowing us to focus on
specific changes rather than automatic re-ordering. You must either add your
entries in the correct order, or add them all at the bottom, then run
`script/translate-alphabetiseAll.sh` to move them into the correct
ordering.

After saving the template file, run
`script/translate-updateFromTemplate.sh` to add the new entries to
all translation files. Then edit the en.po file and add your english
translation.

```
#.The English translation
msgid "my_shiny_new_entry"
msgstr "The English translation"
```


#### To remove an entry

Remove the entry from the programmes.pot template and run
`script/translate-updateFromTemplate.sh`


### Updating translation files with new translations (supplied by a translator in .po format)

There's a script that does most of this for you -
`translate-updateLanguageFromSuppliedPO.sh`. For example, to update the Welsh
translation file (language code "cy") from a new file supplied by a translator:

```sh
./script/translate-updateLanguageFromSuppliedPO.sh ~/Downloads/new-translations-cy_GB.po cy
```

Once you've done that, you'll probably want to run a diff to check everything
is OK and remove any superfluous headers added by the .po editor by hand. But
the script should take care of any changes in ordering or new/wrong entries that
may have been added by the translator's .po editor. It uses msgmerge from the
command line internally.

Profiling
-----------
There is a long-lived (hopefully) profiling branch named profiling-build which brings
in tideways (basically xhprof updated for PHP7), a GUI and a few other things
which you can run on Cosmos INT. It uses https://github.com/bbc/programmes-xhprof to setup the profiling.
 Got to that repository to see the available configuration options.

How to do this:
Checkout the profiling-build branch, rebase it on master and deploy this branch to INT. This assumes that the 
code you want to profile is on master of course. 

Visit /whatever/your/route/is?__profile=1 (Note the double underscore).
Load that at least 5 times to make sure that everything that should be cached is cached.
Now visit /xhprof/xhprof_html/index.php .
You should see a list of your visits along with a load of metrics on execution. 

Fixtures and scenarios
-----------
The codebase has the ability to switch to a fixture database and a set of fixtured HTTP calls dynamically on the
INT and TEST environments. 

To load a scenario, simply visit the fixtured page with the query string __scenario=scenario-name in the URL

To view a page using the fixture database (but not fixtured HTTP calls) use the query string __scenario=browse

To create a scenario/fixtures there are a few steps. Firstly you will need to create a file named "fixture.ini" in
the script folder on your cloud sandbox. Populate it with the following
```bash
PASSWORD="the_password"
```  
you can obtain the password from the usual place.

Now cd to the checkout out programmes frontend code and run ```./script/fixture```. The script can do 3 things, 
documented in the help output. Generate a new scenario (the code will error if the scenario name already exits). 
Regenerate an existing scenario (this basically uses all the existing HTTP fixtures, but creates new ones for any
unfixtured URLs). Or delete a scenario. Have fun!


Developing on fixtures and scenarios
---------------------------------

If you're developing on the scenario and fixture mechanisms you should make sure all DB writes go to your local
database. Make sure all fixture_database_* parameters are pointing at your local database.

The following parameters are especially important: 
* scenario_database_name - This is where HTTP fixtures and scenario name/time/URLs are written. It must be writable by 
fixture_database_user.
* fixture_database_name - Your local copy of the /programmes database

In order to create and populate the scenario datbase, run the following command (will update scenario_database_name, be careful)  
```
php bin/console doctrine:schema:update --em fixture --env dev_fixture
```

Updating the fixture database on test
---------------------------------

The fixture database on test is not kept up to date by the standard faucet migrations. It has to be done 
manually once in a while. To do this, ssh into a FRONTEND server on test with the latest programmes pages service. 
Run the following command to check what needs to be updated  
```
php /var/www/programmes-frontend/bin/console --env=prod_test_fixture doctrine:schema:update  --dump-sql
```
If that looks reasonable, run
```
php /var/www/programmes-frontend/bin/console --env=prod_test_fixture doctrine:schema:update  --force
``` 

Dials
---------------------------------

Cosmos Dials allow functionality to be turned on and off without the need for a release of code. In order to create a 
new dial, add a schema as instructed from 
[the confluence page](https://confluence.dev.bbc.co.uk/display/platform/Developing+with+Dials#DevelopingwithDials-3:WriteaDialSchema). 
Then update [dials.json](dials.json) with the previously used default value. Now you should be ready to go.

Release process
---------------------------------
See [Release Process](docs/release-process.md)

Known Issues
---------------------------------
There is an [issue](https://github.com/symfony/symfony/issues/34467) with a new version of Symfony Translations
so we have locked the translations version to v4.3.2 but this is causing Symfony to be locked as well and we can't
get new version of the framework. There is no easy solution but after expending some time
investigating I found 2 options that could be consider in case of an update is required:

1) Use symfony [ICU format.](https://symfony.com/doc/4.4/translation/message_format.html#pluralization)
This requires to update the translations scripts and some PHP code as well.

2) Implement our own translation system instead of using symfony one, this option could work by only
updating PHP files and keep the existing .po files but with the downside of code becoming
somehow complex and likely to introduce bug that we may not be able to notice for a while

Both options are consider risky, code changes can be somehow complex and issues can go live unnoticed

License
-------

This repository is available under the terms of the Apache 2.0 license.
View the [LICENSE file](LICENSE) file for more information.

Copyright (c) 2017 BBC
