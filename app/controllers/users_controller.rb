class UsersController < ApplicationController
	def show
		render :json => params
		# @user = AsUser.find_by_name(params[:id])
		# render :json => @user.extension
		# render :json => 
	end

	def history
		conversations = converasions_owned_by params[:id]
		# @conversations = Conversation.all
		messages = Array.new
		conversations.each do |c|
			c.messages.each do |m|
				time = (Time.at m.time/1000).to_s(:db)
				jid = (m.direction == "from") ? m.conversation.ownerJid : m.conversation.withJid
				messages.concat [:time => time, :with => m.conversation.withJid, :jid => jid, :body => m.body]
			end
		end
		render :json => { :messages => messages }
	end

	private 
		def converasions_owned_by user
			# Conversation.all.first
			Conversation.where(:ownerJid => jid_of(user))			
		end

		def jid_of user
			"#{user}@avanpbx"
		end
end
