#!/bin/bash

SERVER="giklabs.com"
PS3='Please enter your choice: '
options=("Demo" "CompuExpress" "ArcaCevic" "ArcaServis" "SurtiHogar" "Sysdein" "Maia" "Giancorpf" "DSI" "Las Yolas" "1Break" "Difashbela" "Quit")

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
  "CompuExpress")
    USERNAME="c4_compuexpress"
    deploy
    ;;
  "ArcaCevic")
    USERNAME="c7_arcacevic"
    deploy
    ;;
  "ArcaServis")
    USERNAME="c7_arcaservis"
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
      USERNAME="c8_maia"
      deploy
      ;;
  "Giancorpf")
      USERNAME="c2_giancorpf"
      deploy
      ;;
  "DSI")
      USERNAME="c14_dsi"
      deploy
      ;;
  "Las Yolas")
      USERNAME="c15_lasyolas"
      deploy
      ;;
  "1Break")
      USERNAME="c16_1break"
      deploy
      ;;
  "Difashbela")
      USERNAME="c19_difashbela"
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
