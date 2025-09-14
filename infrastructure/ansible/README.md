
Ansible.cfg

[defaults]
inventory = ./hosts.yml
host_key_checking = False

[ssh_connection]
ssh_args = -o ForwardAgent=yes -o ControlMaster=auto -o ControlPersist=60s



 ~/.ssh/config
Host <HOST-IP>
  ForwardAgent yes


# Installation
https://docs.ansible.com/ansible/latest/installation_guide/

### Install Collection for community.docker 
`ansible-galaxy collection install community.docker`

# Usage
`eval "$(ssh-agent -s)"`
`ssh-add ~/.ssh/github_rsa` or your desired github key

`ansible-playbook playbook/blaze-competition-docker.yaml`

### from Docker VM
https://symfony.localhost:8443/ 