class AsUser < ActiveRecord::Base
	establish_connection "asterisk"
	set_table_name "users"

	attr_accessible :extension, :name, :jid 


	def username
		(/^([^\@]+)/.match(self.jid)).to_s
	end
end
