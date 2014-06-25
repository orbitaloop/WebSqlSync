#This simple sinatra example written in ruby allow to sync two tables things and tags 
#with a javascript app that syncs with WebSqlSync
#Created by Stefano Falda (stefano@babisoft.com)

require 'rubygems'
require 'sinatra'
require 'json'
require 'active_record'
require 'thin'
require 'sqlite3'
require 'pry' #debugger

set :environment, :development
#set :bind, '192.168.0.4' 
set :port, 8086
set :public_folder, './'

ActiveRecord::Base.logger = Logger.new(File.open('database.log', 'w'))

ActiveRecord::Base.establish_connection(
	:adapter  => 'sqlite3',
	:database => 'things.db',
)

ActiveRecord::Schema.define do
	unless ActiveRecord::Base.connection.tables.include? 'things'
		create_table :things do |table|
			table.column :uuid, :string 
			table.column :title,     :string
			table.column :description, :string
			table.column :url, :string
			table.column :created_at, :datetime
			table.column :last_sync_date, :datetime
			table.column :device_id, :string
			table.column :updated_at, :datetime
			table.column :deleted, :boolean , default: 0
		end
	end

	unless ActiveRecord::Base.connection.tables.include? 'tags'
		create_table :tags do |table|
			table.column :uuid, :string 
			table.column :description, :string
			table.column :title, :string
			table.column :color, :string
			table.column :created_at, :datetime
			table.column :updated_at, :datetime
			table.column :last_sync_date, :datetime
			table.column :device_id, :string
			table.column :created_at, :datetime
			table.column :deleted, :boolean, default: 0 
		end
	end
end

class Thing < ActiveRecord::Base
	def as_json(options)
		#Override serialization to json to exclude server only attributes
		super({:except => [:id, :created_at, :device_id, :last_sync_date, :updated_at]})
	end
end

class Tag < ActiveRecord::Base
	#belongs_to :album
end 

after do
	ActiveRecord::Base.clear_active_connections!
end  
 
get '/' do
  Thing.create(:uuid=>SecureRandom.uuid, :title=>"Creato sul Server", :description=>"Descrizione", :device_id=>"SERVER", :last_sync_date=>DateTime.now)
	redirect "index.html"
end

class ServerAnswer 
	attr_accessor :result, :message, :syncDate, :data
	@result
	@message
	@syncDate
	@data
end

get '/sync' do
	#"You should issue a POST!"
	serverAnswer = {
		:result => 'ERROR',
		:message => 'ERROR message'
	}
	serverAnswer.to_json
end

post '/sync', :provides => :json do
	jdata = JSON.parse(request.body.read.to_s)
	info = jdata["info"]
	lastSyncDate = info["lastSyncDate"]
	lastSyncDate = lastSyncDate.to_datetime unless !lastSyncDate or lastSyncDate == 0
	device_id = info["deviceId"]
	new_sync_date = DateTime.now
	puts "Last sync date: #{lastSyncDate}"
	things = jdata["data"]["things"]
	##UPDATE/INSERT DATA ON THE SERVER
	updated_items = 0 
	inserted_items = 0
	things.each do |obj|
		id = obj["uuid"]
		thing = Thing.where("uuid=:uuid", :uuid => id).first
		if !thing
			#INSERT 
			thing = Thing.create(:uuid=>id)
			inserted_items = inserted_items + 1
		else
			updated_items updated_items + 1
		end
		#binding.pry #debug
		thing.title = obj["title"]
		thing.description = obj["description"]
		thing.url = obj["url"]
		thing.device_id = device_id
		thing.last_sync_date = new_sync_date
		thing.save
	end
	##PREPARE DATA TO RETURN
	#Read data to send to client
	things_to_send = Thing.where("last_sync_date>:last_sync_date AND device_id <> :device_id ",
		:last_sync_date=>lastSyncDate, :device_id => device_id) #:new_sync_date=>new_sync_date
	new_items = things_to_send.length
	#binding.pry
	server_answer = {
		:result => 'OK',
		:message => "#{inserted_items} Inserted Items - #{updated_items} Updated items in the server - #{new_items} New items to download",
		:syncDate => new_sync_date,
		:data => {
			:things => things_to_send
		}
	}
	puts "message:#{server_answer['message']}"
	#binding.pry 
	server_answer.to_json
end


not_found do
  halt 404, "You still haven't found what you're looking for"
end