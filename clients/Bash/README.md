# Bash client

The Bash script `upload2section.sh` can be used to upload a local file to the
private file area and make it available in a section of a course.

The script looks for a `moodle.config` file in the current directory. A
template is provided, **make sure to edit it before running the script**.

## Usage

To upload the file "test 1.txt" to section 4 of course 22686:

```
./upload2section.sh 22686 4 "test 1.txt"
```

An additional parameter can be given to specify the display name:

```
./upload2section.sh 22686 4 "test 1.txt" "Test file"
```
