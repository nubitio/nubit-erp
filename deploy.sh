#!/bin/bash

ERROR="Error. Please make sure you've indicated correct parameters"
if [ $# -eq 0 ]; then
  echo "$ERROR"
elif [[ "$1" == "live" ]]; then
  if [[ -z $2 ]]; then
    echo "Running dry-run"
    #rsync --dry-run -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c3_efact@giklabs.com:/web/
    #rsync --dry-run -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c6_sysdein@giklabs.com:/web/
    #rsync --dry-run -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c5_surtihogar@giklabs.com:/web/
    rsync --dry-run -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c7_elcevichero@giklabs.com:/web/
    #rsync --dry-run -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c7_arcacevic@giklabs.com:/web/
  elif [[ "$2" == "go" ]]; then
    echo "Running actual deploy"
    #rsync -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c3_efact@giklabs.com:/web/
    #rsync -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c6_sysdein@giklabs.com:/web/
    #rsync -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c5_surtihogar@giklabs.com:/web/
    rsync -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c7_elcevichero@giklabs.com:/web/
    #rsync -auzP --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ c7_arcacevic@giklabs.com:/web/
  else
    echo "$ERROR"
  fi
fi
