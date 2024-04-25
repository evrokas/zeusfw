Zeus Framework

[25/04/2024] Framework folders have been split, to framework and webpage projects

In the main index PHP script,

1. create a Kernel instance
2. create a Route instance from configuration routes
3. create a ZETEMTemplate instance for templates directories
4. create a RequestClass instance from $_SERVER variable


* call handler from the request
* if request handler returns a markup, render output
* if request performs redirect, move to new page and restart

* traverse all regions

config.yaml
----
regions:
  - header
  - main_navigation
  - main_content
  - footer
----

So for each region render the output and gather it

----
When rendering a region, traverse all blocks and 

Render each block in a region with parameteres
Render a region with all blocks as parameter {{block}}



----
* router

routes:
  homepage:
    title: "Homepage"
    name: homepage
    url: "/"
    handler: homepage
  admin:
    title: "Administration"
    name : admin
    url: "/admin"
    handler: admin
    permissions: authenticated

permissions field might be
 - blank: all users can access the route
 - authorized: all authorized users can access the route (special case)
 - [role]: users with the specified role can access the route,
   these users should always be authenticated
Access permissions and roles are initialized in a different section


