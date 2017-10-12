#!/bin/sh

# WARNING: THIS VERY SCRIPT IS USED BY HUGO ON OWNCLOUD SERVER BACKEND!

if [ "$#" -ne 4 ]; then
	echo "illegal number of parameters"
	echo "syntax: eos-create-user-directory <eos_mgm_url> <eos_user_dir_prefix> <eos_recycle_dir_prefix> <user_id>"
	echo "example:eos-create-user-directory root://eosbackup.cern.ch /eos/scratch/user /eos/scratch/user/proc/recycle <user_id>"
	exit 1
fi


usr=$4

if [ x$usr == x ]; then
   echo missing user
   echo syntax: eos-create-user-directory user
   exit 1
fi

export EOS_MGM_URL=$1
STORAGE_PREFIX=$2
RECYCLE_BIN=$3

#FIXME: protect from local users, root included

initial="$(echo $usr | head -c 1)"
#echo 'setting up home directory for user' $usr

id $usr || (echo "ERROR resolving user" $usr; exit -1)

group=`id -gn $usr`

if [ $? -ne 0 ] ; then 
    echo "ERROR: cannot retrieve group name for the user" $usr; 
    exit -1 
fi

homedir=${STORAGE_PREFIX}/$initial/$usr

#echo 'creating' $homedir
#set -o verbose
eos -b -r 0 0 mkdir -p $homedir
eos -b -r 0 0 chown $usr:$group $homedir
eos -b -r 0 0 chmod 2700 $homedir
eos -b -r 0 0 attr set sys.acl=u:$usr:rwx\!m $homedir # not needed anymore (using sys.owner.auth) # FIXME z:!d
eos -b -r 0 0 attr set sys.mask="700" $homedir
eos -b -r 0 0 attr set sys.allow.oc.sync="1" $homedir
eos -b -r 0 0 attr set sys.mtime.propagation="1" $homedir 
eos -b -r 0 0 attr set sys.forced.atomic="1" $homedir
eos -b -r 0 0 attr set sys.versioning="10" $homedir

eos -b -r 0 0 quota set -u $usr -v 2TB -i 1M -p ${STORAGE_PREFIX}

eos -b -r 0 0 access allow user $usr # this is temporary until we allow all users enter in

#eos -b -r 0 0 attr -r set sys.recycle="$RECYCLE_BIN" $homedir

#eos -b -r 0 0 attr -r rm sys.eval.useracl $homedir

#set +o verbose

#echo 'SUCCESS' $homedir 'created and ownership set to' $usr:$group

# DONE.
