#!/bin/bash

SERVER="giklabs.com"
PS3='Please enter your choice: '
options=("Demo" "ArcaCevic" "ArcaServis" "SurtiHogar" "Sysdein" "Maia" "Giancorpf" "Quit")

function deploy() {
  echo "Running deploy"
  rsync -auzP --force --delete --progress --exclude-from=deploy_exclude.txt -e 'ssh -p22' ./ $USERNAME@$SERVER:/web/
}

function dry-run() {
  echo "Running dry-run"
  rsync --dry-run -auzP --force --progress --exclude-from=deploy_exclude.txt -e "ssh -p22" ./ $USERNAME@$SERVER:/web/
}

select opt in "${options[@]}"; do
  case $opt in
  "Demo")
    USERNAME="c3_efact"
    deploy
    ;;
  "ArcaCevic")
    USERNAME="c7_arcacevic"
    deploy
    ;;
  "ArcaServis")
    USERNAME="c7_elcevichero"
    deploy
    ;;
  "SurtiHogar")
    USERNAME="c5_surtihogar"
    deploy
    ;;
  "Sysdein")
      USERNAME="c6_sysdein"
      deploy
      ;;
  "Maia")
      USERNAME="c4_maia"
      deploy
      ;;
  "Giancorpf")
      USERNAME="c2_giancorpf"
      deploy
      ;;
  "Quit")
    break
    ;;
  *)
    echo "Invalid option $REPLY"
    ;;
  esac
done
