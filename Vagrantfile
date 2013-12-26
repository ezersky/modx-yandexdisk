# -*- mode: ruby -*-
# vi: set ft=ruby :

VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "raring32"
  # config.vm.box_url = "http://domain.com/path/to/above.box"

  config.vm.network :forwarded_port, guest: 80, host: 8085
  # config.vm.network :private_network, ip: "192.168.33.10"
  # config.vm.network :public_network

  config.vm.synced_folder "core/components/yandexdisk", "/var/www/modx/core/components/yandexdisk"

  config.vm.provider :virtualbox do |vb|
    vb.gui = false
    vb.customize ["modifyvm", :id, "--memory", "256"]
  end

  config.vm.provision :shell, :inline => "sudo apt-get update"

  VAGRANT_JSON = JSON.parse(Pathname(__FILE__).dirname.join('_env/chef/nodes', 'vagrant.json').read)

  config.vm.provision :chef_solo do |chef|
    chef.cookbooks_path = ["cookbooks", "_env/chef/cookbooks"]
    chef.roles_path = "_env/chef/roles"
    chef.data_bags_path = "_env/chef/data_bags"
    #chef.provisioning_path = "/tmp/vagrant-chef"

    chef.add_recipe "runit"
    chef.add_recipe "apt"
    chef.add_recipe "php-fpm"
    chef.add_recipe "nginx"
    chef.add_recipe "mysql"
    # chef.add_recipe "modx" // experimental

    chef.json = VAGRANT_JSON
  end
end
