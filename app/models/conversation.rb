class Conversation < ActiveRecord::Base
	establish_connection "openfire"
	set_table_name "archiveConversations"

	attr_accessible :conversationId, :startTime, :endTime, :ownderJid, :withJid
	has_many :messages, :foreign_key => "messageId"

	def messages 
		Message.where(:conversationId => self.conversationId)
	end
end
