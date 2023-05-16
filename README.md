# Sistema Efact #

Sistema desarrollado en PHP

## Arquitectura Clean Code Architecure ##

> SymfonyFramework


## Clonar repositorio ##
>git clone https://github.com/CompuExpress/efact.git

## Clonar el archivo .env ##
>cp .env .env.local

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



## Instalacion de la base de datos local ##

**Instalar navicat u otro IDE de SQL**

**Insertar las credenciales en el  navicat u otro IDE de SQL del archivo .env.local**
       
    DATABASE_URL="mysql://c1_demo:Admin.3000@database:3306/c1_demo?serverVersion=mariadb-10.7.1&charset=utf8mb4"


## Libreria para componentes de vistas ##
 >Jeasyui