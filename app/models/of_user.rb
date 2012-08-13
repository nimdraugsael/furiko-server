class OfUser < ActiveRecord::Base
	establish_connection "openfire"
	set_table_name "ofuser"

	attr_accessible :username, :name, :encrypted_password, :email

end
