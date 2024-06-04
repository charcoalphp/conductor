# Charcoal Conductor
A CLI tool for interacting with the [Charcoal framework](https://github.com/charcoalphp/charcoal).

## Requirements
- php ^7.4
- composer
## Installation
```BASH
composer global config repositories.stecman/symfony-console-completion vcs https://github.com/MouseEatsCat/symfony-console-completion
composer global require charcoal/conductor
```
### Autocompletion
To enable autocompletion, you need to add the following to your `.bashrc` or `.zshrc` file.
```BASH
source <(conductor _completion -g -p conductor)
```
## Commands
| Command            | Description                                                   |
| ------------------ | ------------------------------------------------------------- |
| help               | Display help for a command                                    |
| list               | List commands                                                 |
| models:create      | Create a new Model                                            |
| models:list        | List all registered Models                                    |
| models:sync        | Synchronize the database with model definitions               |
| attachments:create | Create a new Attachment                                       |
| attachments:list   | List all registered Attachments                               |
| attachments:sync   | Synchronize the attachments table with attachment definitions |
| scripts:list       | List all charcoal scripts                                     |
| scripts:run        | Run a charcoal script                                         |
| template:create    | Create a new Template                                         |
| project:create     | Create a new charcoal project                                 |
