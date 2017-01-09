#!/bin/bash

# Delete Indexes
echo "Are you sure you want to delete the whole index? [y/n]"
read yesno
if [[ "$yesno" == "y" ]];then
    echo "Are you sure"
    read confirm_yesno
    if [[ "$confirm_yesno" == "y" ]];then 
        echo "Stopping VuFind"
        ./vufind.sh stop
        echo "rm -rv solr/biblio/data/index"
        rm -rv solr/biblio/data/index
        echo "rm -rv solr/biblio/data/spellchecker"
        rm -rv solr/biblio/data/spellchecker
        echo "rm -rv solr/biblio/data/spellShingle"
        rm -rv solr/biblio/data/spellShingle
        echo "Starting VuFind"
        ./vufind.sh start
    fi
fi
