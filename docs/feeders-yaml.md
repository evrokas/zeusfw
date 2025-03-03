Feeders YAML description file

This file is the basic element for creating repeatable data files
and populating these files with data.

'feeder' is the basic descriptor file
'feed' is the generated file


'feed' files are generated once with 'maker.php feed:gen:yaml' command and argument '-dir feeder-descriptor.yaml'

'feeder' descriptor file structure is as follows:
---
title: view content feeder
        * feeder title

schema: htmlcontent.yaml
        * generated feeds will be based upon the 'schema' database table structure

schemadir: "@core/classes/yaml"
        * location of the schema file, @core is the framewrk 'core/' folder, @app is the basic app folder

content_dir: '/web/content'
        * [not used yet]: possibly location where everyting is stored

source:
  - views
        * folder where generated 'feeds' are placed, can be multiple folders

key:
    * in each feeds there is one basic key [primarily used for language description]
  name: lang
        * name of the key field
  value: [en, gr]
        * values of the key field


guid:
    * which field is named as 'guid', so proper value is generated
  - guid

date:
    * fields that have data value, so proper value is generated
  - cdate
  - created

name:
    * fields that will have their value named as the 'feed' name
  - machinename
  # - name

# sequential:
    * fields that will have assigned a sequential number, starting from 1
  # - seq

prefeed:
    * fields that will be prepopulated with default values
  cuser: guest
  name: 'general-{{name}}'
  machine-name: 'general-{{name}}'
  contentfile: 'view-{{name}}-{{key}}'
  published: 0
  params:
    - tags:
      - '{{leaf}}'      

DEFAULT values can either be a string/number/true/false value, or a special case of array.
arryas in feeders are interpreted in a special way. When generating the key, 

# order: [spinalfusion, kyphoplasty, missdiscectomy, misslaminectomy,
#       spinalbiopsy, roboticfusion,
#       craniotomy, brainendoscopy, arnoldchiarifm, eyebrowcraniotomy, cpcraniotomy,brainbiopsy,
#       braintumorablation]

sections:
    * here are the feeds that will be created, feeds are created on the last leaf of the hiercarchy
    for example: sections: brain: craniotomy, creates a feed with the name craniotomy, {{leaf}} template
    replacement tokens are used in the 'prefeed' section to inject text in the data
    * {{leaf}} means the name of the final leaf
    * {{leaf^}} means the parent of the final leaf
    * {{leaf^^}} means the parent of the parent of the final leaf
    * Tokens until leaf^^^^ has been implemented yet
    * {{key}} is the key value generated
    * {{name}} is the name of the feed generated
    * {{root}} [to be implemented] is the parent field of the leaf hierarchy
    * {{root^}} [to be implemented] is the child of the parent field
    * Tokens until root^^^^ to be implemented
    * {{%}} [to be implemented] sequence number

  brain:
  spine:
