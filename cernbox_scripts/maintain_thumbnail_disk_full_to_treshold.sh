DESIRED_TRESHOLD=$1

if [ $# -eq 0 ]
  then
    echo "No arguments supplied"
    echo "Usage: $0 <treshold>"
    echo "Example that will maintain the disk filled up to 80%: $0 0.8"
    exit 1
fi

# output date of execution
date

# The script can be optimized to avoid multiple finds ...
DISK_FILLED=$(df |  grep var | awk '{print $5}')
DISK_VOLUME_MAX_HUMAN=$(df -h |  grep var | awk '{print $2}')
DISK_VOLUME_USED_HUMAN=$(df -h |  grep var | awk '{print $3}')
DISK_VOLUME_MAX=$(df |  grep var | awk '{print $2}')
DISK_VOLUME_USED=$(df |  grep var | awk '{print $3}')
TOTAL_SIZE_BYTES=$(find /var/thumbnails  -type f -printf "%T+\t%s\t%p\n" | sort -n -k 2 | awk '{TOTAL_SIZE+=$2} END {print TOTAL_SIZE'})
NUMBER_OF_FILES=$(find /var/thumbnails  -type f -printf "%T+\t%s\t%p\n" | wc -l)
AVG_FILE_SIZE_BYTES=$(echo "${TOTAL_SIZE_BYTES} / ${NUMBER_OF_FILES}" | bc -l)

echo "The metrics are given in KiB otherwise specified"
echo "disk_volume_filled=${DISK_FILLED} disk_volume_max=${DISK_VOLUME_MAX_HUMAN} disk_volume_used=${DISK_VOLUME_USED_HUMAN}"
find /var  -type f -printf "%T+\t%s\t%p\n" | sort -n -k 2 | awk '{TOTAL_SIZE+=$2} END {print "number_of_records="NR,"total_size_bytes="TOTAL_SIZE,"avg_file_size_bytes="TOTAL_SIZE/NR}'


# Check if we have hit the treshold
CURRENT_TRESHOLD=$(echo "${DISK_VOLUME_USED}/${DISK_VOLUME_MAX}" | bc -l)
echo "current_treshold=${CURRENT_TRESHOLD} desired_treshold=${DESIRED_TRESHOLD}"

if [ 1 -eq "$(echo "${CURRENT_TRESHOLD} < ${DESIRED_TRESHOLD}" | bc -l)" ]
then  
	echo "We are under the threshold: currentTreshold(${CURRENT_TRESHOLD}) < desiredTreshold(${DESIRED_TRESHOLD})"
	exit 0
fi 
echo "The desired treshold has been exceeded. Estimating number of files to delete..."

DESIRED_VOLUME=$(echo "${DISK_VOLUME_USED} * ${DESIRED_TRESHOLD} / ${CURRENT_TRESHOLD}" | bc -l)
echo "desiredVolume = diskVolumeUsed(${DISK_VOLUME_USED}) * desiredTreshold(${DESIRED_TRESHOLD})/currentTreshold(${CURRENT_TRESHOLD}) = ${DESIRED_VOLUME}"

FILES_TO_DELETE=$(echo "(${DISK_VOLUME_USED} - ${DESIRED_VOLUME}) * 1024 / ${AVG_FILE_SIZE_BYTES}" | bc )
echo "numberOfFilesToDelete = (diskVolumeUsed(${DISK_VOLUME_USED}) - desiredVolume(${DESIRED_VOLUME})) * 1024 / avgFileSize(${AVG_FILE_SIZE_BYTES}) = ${FILES_TO_DELETE}"

if [ ${FILES_TO_DELETE} -eq 0 ]
then
	echo "There are not files to delete because the tresholds are mostly equal"
	exit 0
fi


find /var/thumbnails  -type f -printf "%T+\t%s\t%p\n" | sort -n -k 1 | head -${FILES_TO_DELETE}
find /var/thumbnails  -type f -printf "%T+\t%s\t%p\n" | sort -n -k 1 | head -${FILES_TO_DELETE} | awk '{print "rm "$3}' | sh
