#!/usr/bin/env bash

# Abort on errors, including unset variables
set -eu

# If wrong number of parameters was given, show usage message and exit
if (($# < 3 || $# > 4)); then
  echo "Usage: ${0##*/} <course ID> <section ID> <filename> [<display name>]" >&2
  exit 1
fi

# Assign command-line parameters to named variables
course=$1
section=$2
filename=$3
displayname=${4:-$filename} # Use filename if display name not specified

# Read Moodle configuration, including secret tokens
source moodle.config

# Upload the file
upload_output=$(curl -sS -F "file_1=@${filename}" "https://${moodle_host}/webservice/upload.php?token=${moodle_web_service_token}")

# Get uploaded file ID from cURL output
item_pattern='"itemid":([0-9]+)'
if [[ "${upload_output}" =~ $item_pattern ]]; then
  uploaded_item=${BASH_REMATCH[1]}
  echo "Uploaded \"${filename}\" to item ${uploaded_item}"
else
  echo "Unexpected output for upload of file \"${filename}\": ${upload_output}" >&2
  exit 1
fi

# Put uploaded file in private file area
private_file_output=$(curl -sS "https://${moodle_host}/webservice/rest/server.php?wstoken=${moodle_web_service_token}&moodlewsrestformat=json&wsfunction=core_user_add_user_private_files&draftid=${uploaded_item}")
if [[ "${private_file_output}" == "null" ]]; then
  echo "File placed in private area"
else
  echo "Unexpected output when placing file in private area: ${private_file_output}" >&2
  exit 1
fi

# Add file in course's section
section_output=$(curl -sS "https://${moodle_host}/webservice/rest/server.php?wstoken=${moodle_file_resource_token}&wsfunction=local_ws_fileassistant_create_file_resource&filename=${filename}&courseid=${course}&sectionnumber=${section}&displayname=${displayname}")

# Get resource ID
resource_pattern='resource id ([0-9]+)\.'
if [[ "${section_output}" =~ $resource_pattern ]]; then
  resource=${BASH_REMATCH[1]}
  echo "File added to course ${course} section ${section}, resource ${resource} with display name \"${displayname}\""
else
  echo "Unexpected output when adding file to course's section: ${section_output}" >&2
  exit 1
fi
