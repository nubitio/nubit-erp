# Sistema Efact #

Sistema desarrollado en PHP

## Arquitectura Clean Code Architecure ##

> SymfonyFramework


## Clonar repositorio ##
>git clone https://github.com/CompuExpress/efact.git

## Instalacion del sistema de forma local ##

**Instalar docker**

    En Windows:
        start /w "" "Docker Desktop Installer.exe" install

    En Linux:
        sudo apt install docker-ce

**Configurar proyecto con archivo yml**

Se debe crear el contenedor para nuestro proyecto para que se puedan crear las imagenes que contiene el archivo "docker-compose.yml"


**Instalar composer**

Dentro del contenedor web ejecutar comando para instalar composer
     
     composer install
    
**Instalar npm**

Dentro del contenedor webpack ejecutar comando para instalar npm
     
     npm install
    
**Ejecutar proyecto**

Dentro del contenedor webpack ejecutar comando para correr
     
     npm run watch