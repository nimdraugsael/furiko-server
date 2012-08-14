class DialsController < ApplicationController
	
	def new
		render :json => params
	end
end
