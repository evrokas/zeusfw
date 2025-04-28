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
    tags:
      name: '{{leaf}}'      

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



Special cases for json encoded variables in feeder that control the
rendering of the feeder.

based on json encoded field `params`:

prefeed:
  params:
    content:        <-- defines the content to be rendered
      context_name  <-- context to be created, ie title, introduction
        type: html              <-- render as an html file >
        name: [html file]       <-- html file to render >

        type: feed              <-- use feeds to get data from database >
        name: [table name]      <-- database table to use for data retrieval >

        type: cache             <-- special case, use already populated $cache_table
                                to render previously rendered results>
        name: [not used]        <-- name field is not used in this case >
        
        keys:                   <-- [optional] used to filter data from the database table >
          [table column1]: value1   <-- table field1 which will be matched to value1 value can be {{}}
          [table column2]: value2   <-- table field2 which will be matched to value2

        render:
          text: [column]            <-- table field to be rendered through Renderer
          raw-text: [column]        <-- column to be rendered in raw format>
          template:  [template-name]    <-- use template-name template to render data through Renderer >
          raw-template: [template-name] <-- use template-name template to render data, but in raw format>
          feed: [html file field]  <-- use the html file field, to load an .html file and render it>
          source: [same as above, but newer version, more correct]

          cache:                    <-- special case, put output into $cache_table using 'key' and 'index'
            key: key name of the content    <-- use this name for accessing content from template >
            index: [table column]           <-- used as cache_table main key to descriminate entries>

using 'cache' in [render] key causes all output instead to directly outputted, to be collecte in an array
named $cache_table. This array collects entries from all table rows in first order keys. 
Each table rows has its on first order key. That's is what 'index' is for. All data are indexed according
to different database raw 'render' values.

Each first order key, that is for each row of the database, we have an array with the necessery fields,
ready to be rendered. That is what 'key' stands for, it is the name of the variable that is goind to be stored temporarily in $cache_table.

Actual assignment is:  
    $cache_table[ $el[$rendervalue['index']] ][ $rendervalue['key'] ] = $contentText;

That is $cache_table is a two dimensional table, first key is the contents of the index field of $rendervalue, second key which holds the name of the column of the database, which is going to be late rendered. 

When type=="cache" then function sums up all generated output and renders the results.
render:
  template: [template name]       <-- use that template to render data >
  list: unordered-list|table      <-- how to output data, as unordered-list or html-table or [etc??] >


