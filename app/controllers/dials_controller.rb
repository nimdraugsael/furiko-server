class DialsController < ApplicationController
	
	def new
		ami = RubyAsterisk::AMI.new("avanpbx", 5038)
		response = ami.login("furiko", "123456")
		from = params[:from]
		to = params[:to]
		from_user = AsUser.find_by_name(from)
		if from_user
			from_ext = (from_user) ? from_user.extension : from
			to_user = AsUser.find_by_name(to)
			to_ext = (to_user) ? to_user.extension : to 
			response = ami.originate("SIP/#{from_ext}", "from-internal", "#{to_ext}", "1")
			result = {
				:from => from,
				:from_ext => from_user.extension,
				:to => to,
				:to_ext => to_ext,
				:success => response.success	
			}
		else
			result = {
				:from => from,
				:to => to,
				:success => false
			}	
		end
		render :json => result
	end

	def index
		
	end
end
