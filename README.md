# Charcoal Conductor
A CLI tool for interacting with the [Charcoal framework](https://github.com/charcoalphp/charcoal).

## Requirements
- php ^7.4
- composer
## Installation
```BASH
composer global require mouseeatscat/charcoal-conductor
composer global config repositories.stecman/symfony-console-completion vcs https://github.com/MouseEatsCat/symfony-console-completion
```
### Autocompletion
To enable autocompletion, you need to add the following to your `.bashrc` or `.zshrc` file.

Replace `{PHP_EXE}` with the path of your php executable
```BASH
source <({PHP_EXE} ~/.composer/vendor/mouseeatscat/charcoal-conductor/src/index.php _completion -g -p conductor)
```
## Commands
| Command      | Description                                     |
| ------------ | ----------------------------------------------- |
| help         | Display help for a command                      |
| list         | List commands                                   |
| models:list  | List all registered Models                      |
| models:sync  | Synchronize the database with model definitions |
| scripts:list | List all charcoal scripts                       |
| scripts:run  | Run a charcoal script                           |
