# plugin_uptime for Cacti

## Uptime/restart history

## Author
Petr Macek (petr.macek@kostax.cz)

## Screenshot
![Screenshot_2020-02-04 Console - Devices - (Edit)(1)](https://user-images.githubusercontent.com/26485719/73781128-07cd9e00-4790-11ea-9071-ccd08ecc937b.png)
![Screenshot_2020-02-04 Tree Mode KOSTAX (Kostax - PVT - SQLserver2017 express win)](https://user-images.githubusercontent.com/26485719/73781130-09976180-4790-11ea-8427-b91634272bd8.png)


## Installation
Copy directory uptime to plugins directory
Set file permission (Linux/unix - readable for www server)
Enable plugin (Console -> Plugin management)


## How to use?
You will see information about restart/uptime
- on device edit
- in graphs


## Upgrade
Disable plugin (Console -> Configuration -> Plugins)  
Copy and rewrite files your/cacti/installation/plugins/uptime
Set file permission (Linux/unix - readable for www server)
Enable new version (Console -> Configuration -> Plugins)  


## Possible Bugs or any ideas?
If you find a problem, let me know via github or https://forums.cacti.net


## Changelog
--- 0.2
  Add Uptime Tab
  Add graph display limit (20 restarts max)
--- 0.1
  Beginning


