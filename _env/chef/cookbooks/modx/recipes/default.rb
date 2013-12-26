

template '#{node.nginx.dir}/sites-available/#{node.app.name}.conf' do
  source "nginx.conf.erb"
  mode "0644"
end

nginx_site '#{node.app.name}.conf'

