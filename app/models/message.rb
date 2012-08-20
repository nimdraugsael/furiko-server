class Message < ActiveRecord::Base
	establish_connection "openfire"
	set_table_name "archiveMessages"

	attr_accessible :messageId, :time, :direction, :body
	belongs_to :conversation, :foreign_key => "conversationId"

	Message.inheritance_column = :message_type

end
