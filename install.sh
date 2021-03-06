#!/bin/bash

DIR=`realpath -s "$(cd "$(dirname "$0")" && pwd)/../"`
if [[ -f /etc/redhat-release ]]
then
    PLATFORM="RedHat"
else
    PLATFORM=`uname`
fi

shouldInstall() {
    CONTINUE=0

    if [[ $2 = "true" ]]
    then
        CONTINUE=1
        echo 1
    elif [[ $2 = "false" ]]
    then
        CONTINUE=1
        echo 0
    fi

    while [ $CONTINUE != 1 ]
    do
        read -p "$1 [Y/n] " response
        if [[ $response =~ ^([yY][eE][sS]|[yY])$ ]]
        then
            CONTINUE=1
            echo 1
        elif [[ $response =~ ^([nN][oO]|[nN])$ ]]
        then
            CONTINUE=1
            echo 0
        fi
    done
}

AUTOINSTALL_SOURCE=false

# Create the source paths
if [ ! -d $DIR/Source ]; then
    echo "Making Source Directory"
    mkdir $DIR/Source
    cp $DIR/Lightning/install/.htaccess-protected $DIR/Source/.htaccess
    mkdir $DIR/Templates
    AUTOINSTALL_SOURCE=true
fi

# Install the cache directory.
if [ ! -d $DIR/cache ]; then
    echo "Creating cache directory"
    mkdir $DIR/cache
    cp $DIR/Lightning/install/.htaccess-protected $DIR/cache/.htaccess
fi
if [ `shouldInstall "Install PHP dependencies and set permissions? These are required to run Lightning." $INSTALL_PHP_DEPENDENCIES` -eq 1 ]
then
    cd $DIR/Lightning
    git submodule update --init Vendor/BounceHandler
    git submodule update --init Vendor/htmlpurifier
    git submodule update --init Vendor/PHPMailer
    git submodule update --init Vendor/plancakeEmailParser
    chmod 777 $DIR/Lightning/Vendor/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer
fi

if [ `shouldInstall "Install compass and foundation dependencies? This is needed for advanced scss includes in your source files. But not required for basic scss." $INSTALL_VENDOR` -eq 1 ]
then
    cd $DIR/Lightning
    git submodule update --init Vendor/compass
    git submodule update --init Vendor/foundation
fi

if [ `shouldInstall "Install system dependencies like gulp, node, ruby, compass? These are required for building compiled CSS and JS" $INSTALL_SYSTEM_DEPENDENCIES` -eq 1 ]
then
    if [[ "$PLATFORM" == 'RedHat' ]]; then
        # todo: pick repo based on centos version in /etc/redhat-release
        # 5.x
        # wget http://dl.fedoraproject.org/pub/epel/5/x86_64/epel-release-5-4.noarch.rpm
        # sudo rpm -Uvh epel-release-5*.rpm
        # 6.x
        wget http://dl.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
        sudo rpm -Uvh epel-release-6*.rpm
        # 7.x
        # wget http://dl.fedoraproject.org/pub/epel/7/x86_64/e/epel-release-7-1.noarch.rpm
        # sudo rpm -Uvh epel-release-7*.rpm
        # install for centos
        sudo yum -y update
        sudo yum -y install npm
        sudo yum -y install ruby
        gem install bundle
    elif [[ "$PLATFORM" == 'Linux' ]]; then
        # add repo for nodejs
        sudo apt-get -y install python-software-properties curl ruby-full python-software-properties python g++ make unzip
        sudo curl -sL https://deb.nodesource.com/setup_6.x | sudo -E bash -
        sudo apt-get -y install nodejs

        # Install ruby for compass.
        if [[ -f /etc/debian_version ]]; then
          # Debian
          sudo apt-get -y install bundler rubygems
        else
          # Ubuntu
          sudo apt-get -y install ruby-bundler ruby
        fi
    elif [[ "$PLATFORM" == 'Darwin' ]]; then
        # Install ruby for mac.
        \curl -L https://get.rvm.io | bash -s stable
    fi

    # Install dependencies
    cd $DIR/Lightning
    git submodule update --init Vendor/compass
    git submodule update --init Vendor/foundation

    # Install gems.
    sudo gem install sass
    sudo gem install compass
    sudo gem install foundation

    sudo npm install -g gulp-cli
fi

if [ `shouldInstall "Install lightning source dependencies? This is needed if you intend to rebuild lightning files." $INSTALL_SOURCE_DEPENDENCIES` -eq 1 ]
then

    # Some git repos will time out on git://
    git config --global url.https://.insteadOf git://

    # Install foundation dependencies with npm and bower.
    cd $DIR/Lightning/Vendor/foundation
    sudo npm install -g bower grunt-cli

    # for centos, install with bundler
    if [[ -f /etc/redhat-release || -f /etc/debian_version ]]; then
      sudo bundle install
    fi

    # install other dependences
    npm install
    bower install --allow-root
    grunt build

    # Install jquery Validation
    cd $DIR/Lightning/Vendor/jquery-validation
    sudo npm install
    grunt
fi

if [ `shouldInstall "Install social signin dependencies?" $INSTALL_SOCIAL_SIGNIN_DEPENDENCIES` -eq 1 ]
then
    cd $DIR/Lightning
    git submodule update --init Vendor/googleapiclient
    git submodule update --init Vendor/facebooksdk
    git submodule update --init Vendor/twitterapiclient
fi

GULP_BUILD=false

# Copy Source/Resources.
if [ ! -d $DIR/Source/Resources -o `shouldInstall "Install or Reset the Source/Resources file?" $INSTALL_DEFAULT_SOURCE` -eq 1 ]
then
    echo "Linking compass files"
    cp -r ${DIR}/Lightning/install/Resources ${DIR}/Source/

    cd ${DIR}/Source/Resources
    npm install

    # Install lightning dependencies with gulp
    GULP_BUILD=true
fi

if [ `shouldInstall "Install ckeditor?" $INSTALL_CK_EDITOR` -eq 1 ]
then
    cd $DIR
    if [[ ! -d js ]]
    then
        mkdir js
    fi
    cd $DIR/js

    wget https://download.cksource.com/CKEditor/CKEditor/CKEditor%204.14.0/ckeditor_4.14.0_standard.zip
    unzip ckeditor_4.8.0_standard.zip
    if [[ ! -f $DIR/Source/Resources/sass/ckeditor_contents.scss ]]; then
        cp $DIR/js/ckeditor/contents.css $DIR/Source/Resources/sass/ckeditor_contents.scss
        GULP_BUILD=true
    fi
fi

if [ $GULP_BUILD ]
then
    cd $DIR/Source/Resources
    sudo npm cache clean
    sudo npm install
    gulp build
fi

if [ `shouldInstall "Install ckfinder?" $INSTALL_CKFINDER` -eq 1 ]
then
    # Install ckfinder
    echo 'Missing install instructions'
fi

if [ `shouldInstall "Install tinymce?" $INSTALL_TINYMCE` -eq 1 ]
then
    cd $DIR/js
    wget http://download.ephox.com/tinymce/community/tinymce_4.3.4.zip
    unzip tinymce_4.3.4.zip
    mv tinymce tinymce-remove
    mv tinymce-remove/js/tinymce ./
    rm -rf tinymce-remove
fi

if [ `shouldInstall "Install elfinder?" $INSTALL_ELFINDER` -eq 1 ]
then
    cd $DIR/js
    wget https://github.com/Studio-42/elFinder/archive/2.1.12.zip -O elfinder-2.1.12.zip
    unzip elfinder-2.1.12.zip
    mv elFinder-2.1.12 elfinder
    cp $DIR/Lightning/install/elfinder/elfinder.html $DIR/js/elfinder/elfinder.html
fi

if [ `shouldInstall "Install Lightning index file?" $INSTALL_INDEX` -eq 1 ]
then
    cp $DIR/Lightning/install/index.php $DIR/index.php
fi

if [ `shouldInstall "Install root .htaccess file for Apache web server?" $INSTALL_HTACCESS` -eq 1 ]
then
    cp $DIR/install/.htaccess-router $DIR/.htaccess
fi

# Create the source paths
if [ ! -d $DIR/Source ]; then
    echo "Making Source Directory"
    mkdir $DIR/Source
    cp $DIR/Lightning/install/.htaccess-protected $DIR/Source/.htaccess
fi

# Install the sample config file as active.
# TODO: Add option to reset database config
if [ ! -f $DIR/Source/Config/config.inc.php ]; then
    if [ ! -d $DIR/Source/Config ]; then
        echo "Creating Source/Config directory"
        mkdir $DIR/Source/Config
    fi

    echo "Copying initial config files"
    cp -r $DIR/Lightning/install/Config/* $DIR/Source/Config/

    #Collect database information.
    if [[ -z "$DBHOST" ]]; then
    echo -n "Database host: "; read DBHOST
    echo -n "Database name: "; read DBNAME
    echo -n "Database user: "; read DBUSER
    echo -n "Database password: "; read -s DBPASS
    fi

    echo "Copying sample config file to Source/Config with DB configuration"
    # TODO: This needs to be escaped to prevent sed from using the original line.
    sed "s|'database.*,|'database' => 'mysql:user=${DBUSER};password=${DBPASS};host=${DBHOST};dbname=${DBNAME}',|"\
        <$DIR/Lightning/install/Config/config.inc.php >$DIR/Source/Config/config.inc.php
fi

if [ `shouldInstall "Install default database?" $INSTALL_DATABASE` -eq 1 ]
then
    # Conform the databases
    echo "Conforming the database"
    $DIR/Lightning/lightning database conform-schema
    $DIR/Lightning/lightning database import-defaults
fi

if [ AUTOINSTALL_SOURCE = true ] || [ `shouldInstall "Install/reset default images?" $INSTALL_DEFAULT_IMAGES` -eq 1 ]
then
    # Install main images.
    echo "Copying default images to web root."
    cp -rf $DIR/Lightning/install/images $DIR/
elif [ AUTOINSTALL_SOURCE = true ] || [ `shouldInstall "Install missing default images?"` -eq 1 ]
then
    # The difference is that this won't replace any images.
    echo "Copying default images to web root."
    cp -r $DIR/Lightning/install/images $DIR/
fi

if [ AUTOINSTALL_SOURCE = true ] || [ `shouldInstall "Create an admin user?" $CREATE_ADMIN` -eq 1 ]
then
    # Create admin.
    echo "Creating admin user."
    if [[ ! -z "$ADMIN_EMAIL" ]]; then
        ADMIN_PARAMS=" --user $ADMIN_EMAIL "
    fi
    if [[ ! -z "$ADMIN_PASSWORD" ]]; then
        ADMIN_PARAMS="$ADMIN_PARAMS --password $ADMIN_PASSWORD "
    fi
    echo "$DIR/Lightning/lightning user create-admin $ADMIN_PARAMS"
    $DIR/Lightning/lightning user create-admin $ADMIN_PARAMS
fi

echo "Lightning Installation Complete."
